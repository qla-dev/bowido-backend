<?php

namespace App\Modules\Shared\Services;

use App\Modules\Pallets\Models\Pallet;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BillableDaysCalculator
{
    public function calculate(Pallet $pallet, CarbonInterface $periodStart, CarbonInterface $periodEnd, int $gracePeriodDays): int
    {
        $startDate = Carbon::parse($periodStart)->startOfDay();
        $endDate = Carbon::parse($periodEnd)->startOfDay();
        $gracePeriodDays = max(0, $gracePeriodDays);

        if ($endDate->lt($startDate)) {
            return 0;
        }

        /*
         * The dashboard considers a pallet overdue from last_status_changed_at.
         * Use the same source when the pallet is still in a billable status so a
         * monthly invoice cannot restart its grace period from incomplete or
         * historical audit entries.
         */
        if (
            $pallet->currentStatus?->is_billable
            && $pallet->last_status_changed_at !== null
            && $pallet->last_status_changed_at->lte($endDate->copy()->endOfDay())
        ) {
            $billingStartsAt = $pallet->last_status_changed_at
                ->copy()
                ->startOfDay()
                ->addDays($gracePeriodDays);
            $firstBillableDate = $startDate->copy()->max($billingStartsAt);

            return $firstBillableDate->gt($endDate)
                ? 0
                : $firstBillableDate->diffInDays($endDate) + 1;
        }

        $transitions = $pallet->auditLogs
            ->filter(fn ($auditLog) => $auditLog->new_status_id !== null)
            ->sortBy('created_at')
            ->values();

        $billableDays = 0;
        $billableStreak = $this->billableDaysImmediatelyBefore(
            $transitions,
            $startDate,
            $gracePeriodDays,
        );
        $dateCursor = $startDate->copy();

        while ($dateCursor->lte($endDate)) {
            if ($this->isBillableOn($transitions, $dateCursor)) {
                $billableStreak++;

                if ($billableStreak > $gracePeriodDays) {
                    $billableDays++;
                }
            } else {
                $billableStreak = 0;
            }

            $dateCursor->addDay();
        }

        return $billableDays;
    }

    private function billableDaysImmediatelyBefore($transitions, CarbonInterface $startDate, int $gracePeriodDays): int
    {
        $billableDays = 0;
        $dateCursor = Carbon::parse($startDate)->subDay();

        // A streak longer than the grace period produces the same result, so
        // there is no need to scan arbitrarily far back through audit history.
        while ($billableDays < $gracePeriodDays && $this->isBillableOn($transitions, $dateCursor)) {
            $billableDays++;
            $dateCursor->subDay();
        }

        return $billableDays;
    }

    private function isBillableOn($transitions, CarbonInterface $date): bool
    {
        $effectiveTransition = $transitions
            ->filter(fn ($auditLog) => $auditLog->created_at !== null && $auditLog->created_at->lte(Carbon::parse($date)->endOfDay()))
            ->last();

        return (bool) $effectiveTransition?->newStatus?->is_billable;
    }
}
