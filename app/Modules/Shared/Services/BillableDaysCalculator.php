<?php

namespace App\Modules\Shared\Services;

use App\Modules\Pallets\Models\Pallet;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BillableDaysCalculator
{
    public function calculate(Pallet $pallet, CarbonInterface $periodStart, CarbonInterface $periodEnd, int $gracePeriodDays): int
    {
        $transitions = $pallet->auditLogs
            ->filter(fn ($auditLog) => $auditLog->new_status_id !== null)
            ->sortBy('created_at')
            ->values();

        $billableDays = 0;
        $dateCursor = Carbon::parse($periodStart)->startOfDay();
        $endDate = Carbon::parse($periodEnd)->startOfDay();

        while ($dateCursor->lte($endDate)) {
            $effectiveTransition = $transitions
                ->filter(fn ($auditLog) => $auditLog->created_at !== null && $auditLog->created_at->lte($dateCursor->copy()->endOfDay()))
                ->last();

            if ($effectiveTransition?->newStatus?->is_billable) {
                $billableDays++;
            }

            $dateCursor->addDay();
        }

        return max(0, $billableDays - max(0, $gracePeriodDays));
    }
}
