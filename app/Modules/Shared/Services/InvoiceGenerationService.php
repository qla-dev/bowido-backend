<?php

namespace App\Modules\Shared\Services;

use App\Modules\Invoices\Models\Invoice;
use App\Modules\InvoiceItems\Models\InvoiceItem;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\Enums\InvoiceStatus;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceGenerationService
{
    public function __construct(private readonly BillableDaysCalculator $billableDaysCalculator)
    {
    }

    public function generate(
        User $customer,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        ?CarbonInterface $dueAt = null,
        string $currency = 'EUR',
        ?string $notes = null,
        ?Invoice $invoice = null,
    ): Invoice {
        $customer->loadMissing([
            'customerDetail',
            'pallets.auditLogs.newStatus',
            'pallets.currentStatus',
        ]);

        $customerDetail = $customer->customerDetail;

        if (! $customerDetail || ! $customerDetail->is_active) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected customer must have active customer details.'],
            ]);
        }

        return DB::transaction(function () use ($customer, $periodStart, $periodEnd, $dueAt, $currency, $notes, $invoice, $customerDetail): Invoice {
            $lineItems = $this->buildLineItems(
                customer: $customer,
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                pricePerDay: (float) $customerDetail->default_price_per_day,
                gracePeriodDays: $customerDetail->grace_period_days,
            );

            $invoiceRecord = $invoice instanceof Invoice
                ? Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail()
                : new Invoice();
            $invoiceRecord->fill([
                'user_id' => $customer->id,
                'invoice_number' => $invoice?->invoice_number ?? $this->nextInvoiceNumber(),
                'status' => InvoiceStatus::Issued->value,
                'currency' => strtoupper($currency),
                'period_start' => Carbon::parse($periodStart)->toDateString(),
                'period_end' => Carbon::parse($periodEnd)->toDateString(),
                'issued_at' => $invoice?->issued_at ?? now(),
                'due_at' => $dueAt?->toDateString(),
                'notes' => $notes,
            ]);
            $invoiceRecord->save();

            $invoiceRecord->items()->delete();

            $subtotal = 0.0;

            foreach ($lineItems as $lineItem) {
                $subtotal += $lineItem['amount'];

                $invoiceRecord->items()->create($lineItem);
            }

            $invoiceRecord->forceFill([
                'subtotal_amount' => round($subtotal, 2),
                'total_amount' => round($subtotal, 2),
            ])->save();

            return $invoiceRecord->load(['user.role', 'items.pallet']);
        });
    }

    public function recalculateTotals(Invoice $invoice): Invoice
    {
        $invoice->loadMissing('items');

        $total = $invoice->items->sum(fn (InvoiceItem $item) => (float) $item->amount);

        $invoice->forceFill([
            'subtotal_amount' => round($total, 2),
            'total_amount' => round($total, 2),
        ])->save();

        return $invoice->refresh()->load(['user.role', 'items.pallet']);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildLineItems(
        User $customer,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        float $pricePerDay,
        int $gracePeriodDays,
    ): Collection {
        return $customer->pallets
            ->map(function (Pallet $pallet) use ($periodStart, $periodEnd, $pricePerDay, $gracePeriodDays): ?array {
                $billedDays = $this->billableDaysCalculator->calculate(
                    pallet: $pallet,
                    periodStart: $periodStart,
                    periodEnd: $periodEnd,
                    gracePeriodDays: $gracePeriodDays,
                );

                if ($billedDays <= 0) {
                    return null;
                }

                return [
                    'pallet_id' => $pallet->id,
                    'description' => sprintf('Storage billing for pallet %s', $pallet->qr_code),
                    'period_start' => Carbon::parse($periodStart)->toDateString(),
                    'period_end' => Carbon::parse($periodEnd)->toDateString(),
                    'billed_days' => $billedDays,
                    'price_per_day' => round($pricePerDay, 2),
                    'amount' => round($billedDays * $pricePerDay, 2),
                    'metadata' => [
                        'asset_type' => $pallet->asset_type,
                        'current_status_id' => $pallet->current_status_id,
                    ],
                ];
            })
            ->filter()
            ->values();
    }

    private function nextInvoiceNumber(): string
    {
        $datePrefix = now()->format('Ymd');
        $sequence = Invoice::query()
            ->whereDate('created_at', now()->toDateString())
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('INV-%s-%04d', $datePrefix, $sequence);
    }
}
