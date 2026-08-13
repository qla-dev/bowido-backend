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
     * @return array{total_pallets: int, in_transport: int, overdue_units: int, customer_pickup_units: int, top_overdue_clients: array<int, array{user_id: ?int, client_name: string, overdue_pallets: int, debt_eur: float}>}
     */
    public function summaryFor(?User $actor): array
    {
        $baseQuery = $this->baseQuery($actor);

        return [
            // Pallets reported without a QR code are tracked separately and
            // must not inflate the dashboard's total pallet count.
            'total_pallets' => (clone $baseQuery)
                ->whereNotNull('qr_code')
                ->where('qr_code', '!=', '')
                ->count(),
            'in_transport' => (clone $baseQuery)
                ->whereHas('currentStatus', function (Builder $query): void {
                    $query->whereIn('slug', self::TRANSPORT_STATUS_SLUGS);
                })
                ->count(),
            'overdue_units' => $this->countOverdueUnits($actor),
            'customer_pickup_units' => (clone $baseQuery)
                ->whereHas('currentStatus', fn (Builder $query) => $query->where('slug', 'ophalen-klant'))
                ->count(),
            'top_overdue_clients' => $this->topOverdueClients($actor),
        ];
    }

    /**
     * Calculate recovery totals from the complete database result set. The
     * dashboard's pallet list is paginated, so it must not be used here.
     *
     * @return array<int, array{user_id: ?int, client_name: string, overdue_pallets: int, debt_eur: float}>
     */
    private function topOverdueClients(?User $actor): array
    {
        $today = now()->startOfDay();
        $clients = [];

        $this->baseQuery($actor)
            ->select(['id', 'user_id', 'current_status_id', 'last_status_changed_at'])
            ->whereNotNull('last_status_changed_at')
            ->with([
                'currentStatus:id,is_billable,grace_period_days,price_per_day',
                'user:id,name',
                'user.customerDetail:id,user_id,company_name,grace_period_days,default_price_per_day',
            ])
            ->chunkById(500, function ($pallets) use (&$clients, $today): void {
                foreach ($pallets as $pallet) {
                    if (! $this->isOverdue($pallet, $today)) {
                        continue;
                    }

                    $status = $pallet->currentStatus;
                    $customer = $pallet->user?->customerDetail;
                    $changedAt = $pallet->last_status_changed_at->copy()->startOfDay();
                    $graceDays = $customer?->grace_period_days ?? $status->grace_period_days ?? 0;
                    $overdueDays = (int) $changedAt->diffInDays($today) - $graceDays;
                    $debt = $overdueDays * (float) ($customer?->default_price_per_day ?? $status->price_per_day ?? 0);

                    if ($debt <= 0) {
                        continue;
                    }

                    $clientId = $pallet->user?->id;
                    $clientKey = $clientId ?? 'no-client';
                    if (! isset($clients[$clientKey])) {
                        $clients[$clientKey] = [
                            'user_id' => $clientId,
                            'client_name' => $customer?->company_name ?: $pallet->user?->name ?: 'No client',
                            'overdue_pallets' => 0,
                            'debt_eur' => 0.0,
                        ];
                    }

                    $clients[$clientKey]['overdue_pallets']++;
                    $clients[$clientKey]['debt_eur'] += $debt;
                }
            });

        return collect($clients)
            ->map(function (array $client): array {
                $client['debt_eur'] = round($client['debt_eur'], 2);

                return $client;
            })
            ->sortByDesc('debt_eur')
            ->values()
            ->take(10)
            ->all();
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
