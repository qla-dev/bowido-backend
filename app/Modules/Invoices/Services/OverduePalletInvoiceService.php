<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\Models\Invoice;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\Enums\InvoiceStatus;
use App\Modules\Users\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OverduePalletInvoiceService
{
    public function __construct(private readonly InvoiceDeliveryService $deliveryService) {}

    public function generate(
        Pallet $pallet,
        User $customer,
        CarbonInterface $customerSince,
        int $graceDays,
        int $overdueDays,
        float $pricePerDay,
    ): ?Invoice {
        if ($overdueDays <= 0 || $pricePerDay <= 0 || $this->deliveryService->recipientFor($customer) === null) {
            return null;
        }

        return DB::transaction(function () use ($pallet, $customer, $customerSince, $graceDays, $overdueDays, $pricePerDay): Invoice {
            $periodStart = $customerSince->copy()->startOfDay()->addDays($graceDays + 1);
            $periodEnd = now()->startOfDay();
            $priceCents = (int) round($pricePerDay * 100);
            $amountCents = $overdueDays * $priceCents;

            $invoice = Invoice::query()->create([
                'user_id' => $customer->id,
                'invoice_number' => sprintf('INV-OVD-%s-%04d', now()->format('Ymd'), Invoice::query()->whereDate('created_at', now()->toDateString())->count() + 1),
                'status' => InvoiceStatus::Issued->value,
                'currency' => 'EUR',
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'issued_at' => now(),
                'due_at' => now()->addDays(14)->toDateString(),
                'subtotal_amount' => number_format($amountCents / 100, 2, '.', ''),
                'total_amount' => number_format($amountCents / 100, 2, '.', ''),
                'notes' => __('Automatically created when the overdue pallet left the customer.'),
            ]);

            $invoice->items()->create([
                'pallet_id' => $pallet->id,
                'description' => __('Overdue storage for pallet :qr_code', ['qr_code' => $pallet->qr_code]),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'billed_days' => $overdueDays,
                'price_per_day' => number_format($priceCents / 100, 2, '.', ''),
                'amount' => number_format($amountCents / 100, 2, '.', ''),
                'metadata' => [
                    'automatic_overdue_invoice' => true,
                    'customer_since' => $customerSince->toDateString(),
                    'grace_period_days' => $graceDays,
                    'overdue_days' => $overdueDays,
                ],
            ]);

            return $invoice->load(['user.customerDetail', 'items.pallet']);
        });
    }

    public function send(Invoice $invoice, string $source = 'automatic_overdue_pallet'): string
    {
        return $this->deliveryService->send($invoice, $source);
    }

    /**
     * @return array{invoice: Invoice, recipient: string}
     */
    public function sendForDashboard(Pallet $pallet): array
    {
        $pallet->loadMissing(['user.customerDetail', 'currentStatus']);

        if (
            $pallet->currentStatus?->slug !== 'bij-de-klant'
            || ! $pallet->user instanceof User
            || ! $pallet->last_status_changed_at
        ) {
            throw new \InvalidArgumentException(__('This pallet is not currently eligible for an overdue invoice.'));
        }

        $customerSince = $pallet->last_status_changed_at;
        $graceDays = $pallet->user->customerDetail?->grace_period_days ?? $pallet->currentStatus->grace_period_days ?? 0;
        $overdueDays = max(0, $customerSince->copy()->startOfDay()->diffInDays(now()->startOfDay()) - $graceDays);
        $pricePerDay = (float) ($pallet->user->customerDetail?->default_price_per_day ?? $pallet->currentStatus->price_per_day ?? 0);

        Log::info('Dashboard overdue invoice send requested.', [
            'pallet_id' => $pallet->id,
            'customer_id' => $pallet->user->id,
            'overdue_days' => $overdueDays,
            'price_per_day' => $pricePerDay,
        ]);

        $invoice = $this->pendingAutomaticInvoiceFor($pallet)
            ?? $this->generate($pallet, $pallet->user, $customerSince, $graceDays, $overdueDays, $pricePerDay);

        if ($invoice === null) {
            Log::warning('Dashboard overdue invoice was not sent: no valid recipient, overdue amount, or rate.', [
                'pallet_id' => $pallet->id,
            ]);
            throw new \InvalidArgumentException(__('A valid billing recipient, overdue period, and daily rate are required.'));
        }

        if ($invoice->exists && $invoice->wasRecentlyCreated === false) {
            Log::info('Dashboard overdue invoice retrying an existing unsent invoice.', [
                'pallet_id' => $pallet->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);
        }

        try {
            $recipient = $this->send($invoice, 'dashboard_overdue_pallet');
        } catch (\Throwable $exception) {
            Log::error('Dashboard overdue invoice delivery failed.', [
                'pallet_id' => $pallet->id,
                'invoice_id' => $invoice->id,
                'exception' => $exception,
            ]);

            throw $exception;
        }

        Log::info('Dashboard overdue invoice delivered.', [
            'pallet_id' => $pallet->id,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'recipient' => $recipient,
        ]);

        return ['invoice' => $invoice, 'recipient' => $recipient];
    }

    private function pendingAutomaticInvoiceFor(Pallet $pallet): ?Invoice
    {
        return Invoice::query()
            ->where('status', InvoiceStatus::Issued->value)
            ->whereHas('items', fn ($query) => $query->where('pallet_id', $pallet->id))
            ->with(['user.customerDetail', 'items.pallet'])
            ->latest('id')
            ->get()
            ->first(fn (Invoice $invoice): bool => $invoice->items->contains(
                fn ($item): bool => $item->pallet_id === $pallet->id
                    && ($item->metadata['automatic_overdue_invoice'] ?? false) === true
            ));
    }
}
