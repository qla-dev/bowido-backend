<?php

namespace App\Services;

use App\Models\Pallet;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BillableDaysCalculator
{
    public function calculate(Pallet $pallet, CarbonInterface $periodStart, CarbonInterface $periodEnd, int $gracePeriodDays): int
    {
        if (Carbon::parse($periodStart)->startOfDay()->gt(Carbon::parse($periodEnd)->startOfDay())) {
            return 0;
        }

        return $this->walkTimeline(
            pallet: $pallet,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            gracePeriodDays: max(0, $gracePeriodDays),
            ignoreGracePeriod: false,
        );
    }

    public function countBillableDays(Pallet $pallet, CarbonInterface $periodStart, CarbonInterface $periodEnd): int
    {
        if (Carbon::parse($periodStart)->startOfDay()->gt(Carbon::parse($periodEnd)->startOfDay())) {
            return 0;
        }

        return $this->walkTimeline(
            pallet: $pallet,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            gracePeriodDays: 0,
            ignoreGracePeriod: true,
        );
    }

    private function walkTimeline(
        Pallet $pallet,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        int $gracePeriodDays,
        bool $ignoreGracePeriod,
    ): int {
        $transitions = $this->transitions($pallet);
        $startDate = $this->timelineStart($pallet, $periodStart, $transitions);
        $requestedStart = Carbon::parse($periodStart)->startOfDay();
        $endDate = Carbon::parse($periodEnd)->startOfDay();

        $transitionIndex = 0;
        $currentStatus = null;
        $billableDaysSeen = 0;
        $countedDays = 0;
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            while (
                isset($transitions[$transitionIndex])
                && $transitions[$transitionIndex]['date']->lte($cursor->copy()->endOfDay())
            ) {
                $currentStatus = $transitions[$transitionIndex]['status'];
                $transitionIndex++;
            }

            if ($currentStatus?->is_billable) {
                $billableDaysSeen++;

                if (
                    $cursor->gte($requestedStart)
                    && ($ignoreGracePeriod || $billableDaysSeen > $gracePeriodDays)
                ) {
                    $countedDays++;
                }
            }

            $cursor->addDay();
        }

        return $countedDays;
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

    /**
     * @param  Collection<int, array{date: CarbonInterface, status: \App\Models\Status}>  $transitions
     */
    private function timelineStart(Pallet $pallet, CarbonInterface $periodStart, Collection $transitions): CarbonInterface
    {
        $earliestTransition = $transitions->first()['date'] ?? null;
        $fallbackStart = Carbon::parse($pallet->created_at ?? $periodStart)->startOfDay();

        return Carbon::parse($earliestTransition ?? $fallbackStart)->min(Carbon::parse($periodStart))->startOfDay();
    }
}
