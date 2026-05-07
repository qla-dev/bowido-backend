<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Pallet;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BillingCounterService
{
    public function __construct(private readonly BillableDaysCalculator $billableDaysCalculator)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function calculateForPallet(Pallet $pallet, ?CarbonInterface $asOf = null): array
    {
        $pallet->loadMissing([
            'user.customerDetail',
            'auditLogs.newStatus',
            'currentStatus',
        ]);

        $asOfDate = Carbon::parse($asOf ?? now())->startOfDay();
        $customer = $pallet->user;
        $customerDetail = $customer?->customerDetail;
        $pricePerDay = (float) ($customerDetail?->default_price_per_day ?? 0);
        $gracePeriodDays = (int) ($customerDetail?->grace_period_days ?? 0);
        $currentCycleStart = $this->currentBillableCycleStart($pallet, $asOfDate);
        $unbilledStart = $this->defaultBillingPeriodStartForPallet($pallet, $asOfDate);
        $activeDays = $currentCycleStart instanceof CarbonInterface
            ? $this->billableDaysCalculator->countBillableDays($pallet, $currentCycleStart, $asOfDate)
            : 0;
        $billableDays = $this->billableDaysCalculator->calculate($pallet, $unbilledStart, $asOfDate, $gracePeriodDays);
        $currentAmount = round($billableDays * $pricePerDay, 2);

        return [
            'pallet_id' => $pallet->id,
            'customer_id' => $customer?->id,
            'qr_code' => $pallet->qr_code,
            'status_slug' => $pallet->currentStatus?->slug,
            'is_billable_status' => (bool) $pallet->currentStatus?->is_billable,
            'active_days' => $activeDays,
            'grace_period_days' => $gracePeriodDays,
            'billable_days' => $billableDays,
            'overdue_days' => max(0, $activeDays - $gracePeriodDays),
            'price_per_day' => number_format($pricePerDay, 2, '.', ''),
            'current_amount' => number_format($currentAmount, 2, '.', ''),
            'last_billing_period_end' => $customer !== null
                ? $this->latestBillingPeriodEnd($customer, $asOfDate)?->toDateString()
                : null,
            'unbilled_period_start' => $unbilledStart->toDateString(),
            'unbilled_period_end' => $asOfDate->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function calculateForCustomer(User $customer, ?CarbonInterface $asOf = null): array
    {
        $customer->loadMissing([
            'customerDetail',
            'pallets.auditLogs.newStatus',
            'pallets.currentStatus',
        ]);

        $asOfDate = Carbon::parse($asOf ?? now())->startOfDay();
        $palletCounters = $customer->pallets
            ->map(fn (Pallet $pallet): array => $this->calculateForPallet($pallet, $asOfDate))
            ->values();
        $totalAmount = $palletCounters->sum(fn (array $counter): float => (float) $counter['current_amount']);
        $totalBillableDays = $palletCounters->sum('billable_days');
        $overduePallets = $palletCounters->where('overdue_days', '>', 0)->count();

        return [
            'customer_id' => $customer->id,
            'customer_name' => $customer->customerDetail?->company_name ?? $customer->name,
            'last_billing_period_end' => $this->latestBillingPeriodEnd($customer, $asOfDate)?->toDateString(),
            'as_of' => $asOfDate->toDateString(),
            'total_billable_days' => $totalBillableDays,
            'total_amount' => number_format((float) $totalAmount, 2, '.', ''),
            'overdue_pallet_count' => $overduePallets,
            'pallets' => $palletCounters->all(),
        ];
    }

    public function defaultBillingPeriodStartForCustomer(User $customer, CarbonInterface $periodEnd): CarbonInterface
    {
        $latestBillingPeriodEnd = $this->latestBillingPeriodEnd($customer, $periodEnd);

        if ($latestBillingPeriodEnd instanceof CarbonInterface) {
            return $latestBillingPeriodEnd->copy()->addDay()->startOfDay();
        }

        $customer->loadMissing([
            'pallets.auditLogs.newStatus',
            'pallets.currentStatus',
        ]);

        $fallback = $customer->pallets
            ->map(fn (Pallet $pallet): CarbonInterface => $this->earliestRelevantDate($pallet))
            ->sort()
            ->first();

        return Carbon::parse($fallback ?? $periodEnd)->startOfDay();
    }

    private function defaultBillingPeriodStartForPallet(Pallet $pallet, CarbonInterface $periodEnd): CarbonInterface
    {
        $customer = $pallet->user;

        if ($customer !== null) {
            $latestBillingPeriodEnd = $this->latestBillingPeriodEnd($customer, $periodEnd);

            if ($latestBillingPeriodEnd instanceof CarbonInterface) {
                return $latestBillingPeriodEnd->copy()->addDay()->startOfDay();
            }
        }

        return $this->earliestRelevantDate($pallet);
    }

    private function latestBillingPeriodEnd(User $customer, CarbonInterface $periodEnd): ?CarbonInterface
    {
        $date = Invoice::query()
            ->where('user_id', $customer->id)
            ->whereNotNull('billing_period_end')
            ->whereDate('billing_period_end', '<=', $periodEnd->toDateString())
            ->max('billing_period_end');

        return is_string($date) ? Carbon::parse($date)->startOfDay() : null;
    }

    private function currentBillableCycleStart(Pallet $pallet, CarbonInterface $asOf): ?CarbonInterface
    {
        $transitions = $this->transitions($pallet)
            ->filter(fn (array $transition): bool => $transition['date']->lte($asOf))
            ->values();

        if ($transitions->isEmpty()) {
            return $pallet->currentStatus?->is_billable
                ? Carbon::parse($pallet->created_at ?? $asOf)->startOfDay()
                : null;
        }

        $currentCycleStart = null;
        $isInBillableStatus = false;

        foreach ($transitions as $transition) {
            $status = $transition['status'];

            if ($status->is_billable) {
                if (! $isInBillableStatus) {
                    $currentCycleStart = $transition['date']->copy()->startOfDay();
                }

                $isInBillableStatus = true;
                continue;
            }

            $currentCycleStart = null;
            $isInBillableStatus = false;
        }

        return $isInBillableStatus ? $currentCycleStart : null;
    }

    private function earliestRelevantDate(Pallet $pallet): CarbonInterface
    {
        $firstAuditDate = $pallet->auditLogs
            ->sortBy('created_at')
            ->first()?->created_at;

        return Carbon::parse($firstAuditDate ?? $pallet->created_at ?? now())->startOfDay();
    }

    /**
     * @return Collection<int, array{date: CarbonInterface, status: \App\Models\Status}>
     */
    private function transitions(Pallet $pallet): Collection
    {
        $transitions = $pallet->auditLogs
            ->filter(fn ($auditLog) => $auditLog->new_status_id !== null && $auditLog->newStatus !== null)
            ->sortBy('created_at')
            ->map(fn ($auditLog): array => [
                'date' => Carbon::parse($auditLog->created_at ?? $pallet->created_at ?? now()),
                'status' => $auditLog->newStatus,
            ])
            ->values();

        if ($transitions->isEmpty() && $pallet->currentStatus !== null) {
            return collect([[
                'date' => Carbon::parse($pallet->created_at ?? now()),
                'status' => $pallet->currentStatus,
            ]]);
        }

        return $transitions;
    }
}
