<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\Models\Invoice;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Pallets\Rules\PalletCustomerAssignmentRule;
use App\Modules\Shared\Enums\InvoiceStatus;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
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
        ?CarbonInterface $billedThrough = null,
    ): ?Invoice {
        if ($overdueDays <= 0 || $pricePerDay <= 0) {
            return null;
        }

        $billingDate = Carbon::parse($billedThrough ?? now())->startOfDay();
        $invoicePeriodStart = $billingDate->copy()->startOfMonth();
        $invoicePeriodEnd = $billingDate->copy()->endOfMonth()->startOfDay();
        $firstOverdueDate = Carbon::parse($customerSince)->startOfDay()->addDays(max(0, $graceDays) + 1);
        $itemPeriodStart = $firstOverdueDate->copy()->max($invoicePeriodStart);
        $itemPeriodEnd = $billingDate->copy()->min($invoicePeriodEnd);

        if ($itemPeriodStart->gt($itemPeriodEnd)) {
            return null;
        }

        $billedDays = $itemPeriodStart->diffInDays($itemPeriodEnd) + 1;

        return DB::transaction(function () use ($pallet, $customer, $customerSince, $graceDays, $pricePerDay, $invoicePeriodStart, $invoicePeriodEnd, $itemPeriodStart, $itemPeriodEnd, $billedDays): Invoice {
            // Locking the customer serializes invoice creation for this customer,
            // including the case where the monthly invoice does not exist yet.
            User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            $priceCents = (int) round($pricePerDay * 100);
            $amountCents = $billedDays * $priceCents;

            $monthlyInvoices = Invoice::query()
                ->where('user_id', $customer->id)
                ->where(function ($query) use ($invoicePeriodStart, $invoicePeriodEnd): void {
                    $query
                        ->where(function ($periodQuery) use ($invoicePeriodStart, $invoicePeriodEnd): void {
                            $periodQuery
                                ->whereDate('period_start', $invoicePeriodStart->toDateString())
                                ->whereDate('period_end', $invoicePeriodEnd->toDateString());
                        })
                        ->orWhere(function ($legacyQuery) use ($invoicePeriodStart, $invoicePeriodEnd): void {
                            $legacyQuery
                                ->where('invoice_number', 'like', 'INV-OVD-%')
                                ->where('created_at', '>=', $invoicePeriodStart)
                                ->where('created_at', '<=', $invoicePeriodEnd->copy()->endOfDay());
                        });
                })
                ->with('items')
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $invoice = $monthlyInvoices->first();

            if (! $invoice instanceof Invoice) {
                $invoice = Invoice::query()->create([
                    'user_id' => $customer->id,
                    'invoice_number' => $this->nextInvoiceNumber(),
                    'status' => InvoiceStatus::Issued->value,
                    'currency' => 'EUR',
                    'period_start' => $invoicePeriodStart->toDateString(),
                    'period_end' => $invoicePeriodEnd->toDateString(),
                    'issued_at' => now(),
                    'due_at' => $invoicePeriodEnd->copy()->addDays(14)->toDateString(),
                    'subtotal_amount' => 0,
                    'total_amount' => 0,
                    'notes' => __('Automatically created for overdue pallet storage during this calendar month.'),
                ]);
            } else {
                $this->mergeMonthlyDuplicates($invoice, $monthlyInvoices->skip(1));
                $invoice->forceFill([
                    'period_start' => $invoicePeriodStart->toDateString(),
                    'period_end' => $invoicePeriodEnd->toDateString(),
                ])->save();
            }

            $invoice->items()->updateOrCreate(['pallet_id' => $pallet->id], [
                'description' => __('Overdue storage for pallet :qr_code', ['qr_code' => $pallet->qr_code]),
                'period_start' => $itemPeriodStart->toDateString(),
                'period_end' => $itemPeriodEnd->toDateString(),
                'billed_days' => $billedDays,
                'price_per_day' => number_format($priceCents / 100, 2, '.', ''),
                'amount' => number_format($amountCents / 100, 2, '.', ''),
                'metadata' => [
                    'automatic_overdue_invoice' => true,
                    'customer_since' => $customerSince->toDateString(),
                    'grace_period_days' => $graceDays,
                    'overdue_days' => $billedDays,
                    'billing_month' => $invoicePeriodStart->format('Y-m'),
                ],
            ]);

            $totalCents = $invoice->items()
                ->get(['amount'])
                ->sum(fn ($item): int => (int) round((float) $item->amount * 100));

            $invoice->forceFill([
                'subtotal_amount' => number_format($totalCents / 100, 2, '.', ''),
                'total_amount' => number_format($totalCents / 100, 2, '.', ''),
            ])->save();

            return $invoice->load(['user.customerDetail', 'items.pallet']);
        });
    }

    /**
     * Create or update one invoice per customer for pallets that are still
     * overdue at the customer during the requested calendar month.
     *
     * @return Collection<int, Invoice>
     */
    public function generateForMonth(CarbonInterface $month): Collection
    {
        $periodEnd = Carbon::parse($month)->endOfMonth()->startOfDay();
        $periodStart = $periodEnd->copy()->startOfMonth();
        $invoices = collect();

        Pallet::query()
            ->whereNotNull('user_id')
            ->whereRaw('COALESCE(customer_timer_started_at, last_status_changed_at) <= ?', [$periodEnd->copy()->endOfDay()])
            ->whereHas('currentStatus', fn ($query) => $query->whereIn('slug', PalletCustomerAssignmentRule::ALLOWED_STATUS_SLUGS))
            ->with(['user.customerDetail', 'currentStatus'])
            ->orderBy('id')
            ->chunkById(500, function ($pallets) use ($periodStart, $periodEnd, $invoices): void {
                foreach ($pallets as $pallet) {
                    $customerSince = $pallet->customer_timer_started_at ?? $pallet->last_status_changed_at;

                    if (! $pallet->user instanceof User || ! $customerSince) {
                        continue;
                    }

                    $billingThrough = $pallet->customer_timer_frozen_at
                        ? $pallet->customer_timer_frozen_at->copy()->min($periodEnd)
                        : $periodEnd;

                    if (
                        $billingThrough->lt($customerSince->copy()->startOfDay())
                        || $billingThrough->lt($periodStart)
                    ) {
                        continue;
                    }

                    $graceDays = $pallet->user->customerDetail?->grace_period_days
                        ?? $pallet->currentStatus?->grace_period_days
                        ?? 0;
                    $overdueDays = max(
                        0,
                        $customerSince->copy()->startOfDay()->diffInDays($billingThrough->copy()->startOfDay()) - $graceDays,
                    );
                    $pricePerDay = (float) ($pallet->user->customerDetail?->default_price_per_day
                        ?? $pallet->currentStatus?->price_per_day
                        ?? 0);

                    $invoice = $this->generate(
                        pallet: $pallet,
                        customer: $pallet->user,
                        customerSince: $customerSince,
                        graceDays: $graceDays,
                        overdueDays: $overdueDays,
                        pricePerDay: $pricePerDay,
                        billedThrough: $billingThrough,
                    );

                    if ($invoice instanceof Invoice) {
                        $invoices->put($invoice->id, $invoice);
                    }
                }
            });

        return $invoices->values();
    }

    /**
     * @param  Collection<int, Invoice>  $duplicates
     */
    private function mergeMonthlyDuplicates(Invoice $invoice, Collection $duplicates): void
    {
        foreach ($duplicates as $duplicate) {
            foreach ($duplicate->items as $duplicateItem) {
                $existingItem = $invoice->items()->where('pallet_id', $duplicateItem->pallet_id)->first();

                if ($existingItem === null) {
                    $duplicateItem->forceFill(['invoice_id' => $invoice->id])->save();

                    continue;
                }

                if ($duplicateItem->billed_days > $existingItem->billed_days) {
                    $existingItem->fill($duplicateItem->only([
                        'description',
                        'period_start',
                        'period_end',
                        'billed_days',
                        'price_per_day',
                        'amount',
                        'metadata',
                    ]))->save();
                }

                $duplicateItem->delete();
            }

            if ($this->statusRank($duplicate->status) > $this->statusRank($invoice->status)) {
                $invoice->status = $duplicate->status;
            }

            $invoice->mailed_at = collect([$invoice->mailed_at, $duplicate->mailed_at])->filter()->max();
            $invoice->paid_at = collect([$invoice->paid_at, $duplicate->paid_at])->filter()->max();
            $invoice->save();
            $duplicate->delete();
        }
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            InvoiceStatus::Paid->value => 4,
            InvoiceStatus::Sent->value => 3,
            InvoiceStatus::Issued->value => 2,
            InvoiceStatus::Draft->value => 1,
            default => 0,
        };
    }

    private function nextInvoiceNumber(): string
    {
        $datePrefix = now()->format('Ymd');
        $sequence = Invoice::query()
            ->whereDate('created_at', now()->toDateString())
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('INV-OVD-%s-%04d', $datePrefix, $sequence);
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

        $customerSince = $pallet->customer_timer_started_at ?? $pallet->last_status_changed_at;
        $graceDays = $pallet->user->customerDetail?->grace_period_days ?? $pallet->currentStatus->grace_period_days ?? 0;
        $overdueDays = max(0, $customerSince->copy()->startOfDay()->diffInDays(now()->startOfDay()) - $graceDays);
        $pricePerDay = (float) ($pallet->user->customerDetail?->default_price_per_day ?? $pallet->currentStatus->price_per_day ?? 0);

        Log::info('Dashboard overdue invoice send requested.', [
            'pallet_id' => $pallet->id,
            'customer_id' => $pallet->user->id,
            'overdue_days' => $overdueDays,
            'price_per_day' => $pricePerDay,
        ]);

        $invoice = $this->generate($pallet, $pallet->user, $customerSince, $graceDays, $overdueDays, $pricePerDay);

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
}
