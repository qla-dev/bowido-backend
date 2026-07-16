<?php

namespace App\Modules\Pallets\Services;

use App\Modules\Pallets\Models\Pallet;
use App\Modules\Pallets\Rules\PalletCustomerAssignmentRule;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PalletDashboardStatsService
{
    private const TRANSPORT_STATUS_SLUGS = [
        'bih-nl-transport',
        'nl-bih-transport',
    ];

    /**
     * @return array{total_pallets: int, in_transport: int, overdue_units: int}
     */
    public function summaryFor(?User $actor): array
    {
        $baseQuery = $this->baseQuery($actor);

        return [
            'total_pallets' => (clone $baseQuery)->count(),
            'in_transport' => (clone $baseQuery)
                ->whereHas('currentStatus', function (Builder $query): void {
                    $query->whereIn('slug', self::TRANSPORT_STATUS_SLUGS);
                })
                ->count(),
            'overdue_units' => $this->countOverdueUnits($actor),
        ];
    }

    private function countOverdueUnits(?User $actor): int
    {
        $today = now()->startOfDay();
        $overdueUnits = 0;

        $this->baseQuery($actor)
            ->select(['id', 'user_id', 'current_status_id', 'last_status_changed_at'])
            ->with([
                'currentStatus:id,slug,is_billable,grace_period_days',
                'user:id',
                'user.customerDetail:id,user_id,grace_period_days',
            ])
            ->whereNotNull('last_status_changed_at')
            ->chunkById(500, function ($pallets) use (&$overdueUnits, $today): void {
                foreach ($pallets as $pallet) {
                    if ($this->isOverdue($pallet, $today)) {
                        $overdueUnits++;
                    }
                }
            });

        return $overdueUnits;
    }

    private function isOverdue(Pallet $pallet, Carbon $today): bool
    {
        $status = $pallet->currentStatus;

        if (! $status?->is_billable || ! $pallet->last_status_changed_at) {
            return false;
        }

        $changedAt = $pallet->last_status_changed_at->copy()->startOfDay();

        if ($changedAt->greaterThan($today)) {
            return false;
        }

        $graceDays = $pallet->user?->customerDetail?->grace_period_days ?? $status->grace_period_days ?? 0;
        $daysSinceChange = (int) $changedAt->diffInDays($today);

        return $daysSinceChange > $graceDays;
    }

    private function baseQuery(?User $actor): Builder
    {
        $query = Pallet::query();

        if ($actor?->isCustomer()) {
            $query
                ->where('user_id', $actor->id)
                ->whereHas('currentStatus', fn (Builder $statusQuery) => $statusQuery->whereIn(
                    'slug',
                    PalletCustomerAssignmentRule::ALLOWED_STATUS_SLUGS,
                ));
        }

        return $query;
    }
}
