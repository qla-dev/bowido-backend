<?php

namespace App\Modules\Pallets\Repositories;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\GhostPalletReports\Models\GhostPalletReport;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Pallets\Rules\PalletCustomerAssignmentRule;
use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Statuses\Models\Status;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PalletRepository extends BaseRepository
{
    protected function allowedFilters(): array
    {
        return [
            'user_id' => 'user_id',
            'current_status_id' => 'current_status_id',
            'asset_type' => 'asset_type',
            'qr_code' => 'qr_code',
            'reference_code' => 'reference_code',
            'current_location' => 'current_location',
            'is_active' => 'is_active',
            'is_ghost' => 'is_ghost',
            'has_qr_code' => function (Builder $query, mixed $value): void {
                if ((bool) $value) {
                    $query
                        ->where('is_ghost', false)
                        ->whereNotNull('qr_code')
                        ->where('qr_code', '!=', '');

                    return;
                }

                $query->where(function (Builder $noQrQuery): void {
                    $noQrQuery
                        ->where('is_ghost', true)
                        ->orWhereNull('qr_code')
                        ->orWhere('qr_code', '');
                });
            },
            'is_for_repair' => 'is_for_repair',
            'updated_since' => function (Builder $query, mixed $value): void {
                // Use an inclusive boundary so records written in the same
                // database timestamp tick as the cursor are not missed.
                $query->where('updated_at', '>=', date_create_immutable((string) $value));
            },
        ];
    }

    protected function relations(): array
    {
        return ['user.customerDetail', 'currentStatus', 'deliveryLocation', 'ghostPalletReport'];
    }

    protected function scopeForActor(Builder $query, ?User $actor): Builder
    {
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

    protected function applySearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        $like = "%{$search}%";

        return $query->where(function (Builder $builder) use ($like): void {
            $builder
                ->where('pallet_name', 'like', $like)
                ->orWhere('reference_code', 'like', $like)
                ->orWhere('qr_code', 'like', $like)
                ->orWhere('type', 'like', $like)
                ->orWhere('asset_type', 'like', $like)
                ->orWhere('current_location', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhereHas('user', function (Builder $userQuery) use ($like): void {
                    $userQuery
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhereHas('customerDetail', function (Builder $detailQuery) use ($like): void {
                            $detailQuery
                                ->where('company_name', 'like', $like)
                                ->orWhere('kvk', 'like', $like);
                        });
                })
                ->orWhereHas('currentStatus', function (Builder $statusQuery) use ($like): void {
                    $statusQuery
                        ->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like);
                });
        });
    }

    protected function allowedSorts(): array
    {
        return [
            'qr' => fn (Builder $query, string $direction) => $this->orderByPalletDisplayName($query, $direction),
            'pallet' => fn (Builder $query, string $direction) => $this->orderByPalletDisplayName($query, $direction),
            'pallet_name' => fn (Builder $query, string $direction) => $this->orderByPalletDisplayName($query, $direction),
            'reference_code' => 'reference_code',
            'type' => 'type',
            'asset_type' => 'asset_type',
            'status' => fn (Builder $query, string $direction) => $query->orderBy(
                Status::query()
                    ->select('name')
                    ->whereColumn('statuses.id', 'pallets.current_status_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'client' => fn (Builder $query, string $direction) => $query->orderBy(
                CustomerDetail::query()
                    ->select('company_name')
                    ->whereColumn('customer_details.user_id', 'pallets.user_id')
                    ->limit(1),
                $direction,
            )->orderBy(
                User::query()
                    ->select('name')
                    ->whereColumn('users.id', 'pallets.user_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'location' => 'current_location',
            'lastUpdate' => fn (Builder $query, string $direction) => $this->orderBySentDate($query, $direction),
            'last_update' => fn (Builder $query, string $direction) => $this->orderBySentDate($query, $direction),
            'dueDate' => fn (Builder $query, string $direction) => $this->orderByDueDate($query, $direction),
            'due_date' => fn (Builder $query, string $direction) => $this->orderByDueDate($query, $direction),
            'deadline' => fn (Builder $query, string $direction) => $this->orderByDueDate($query, $direction),
            'daysOut' => fn (Builder $query, string $direction) => $this->orderByDueDate($query, $direction),
            'days_out' => fn (Builder $query, string $direction) => $this->orderByDueDate($query, $direction),
            'overdueDays' => fn (Builder $query, string $direction) => $this->orderByDueDate($query, $direction),
            'overdue_days' => fn (Builder $query, string $direction) => $this->orderByDueDate($query, $direction),
            'debt' => 'debt_eur',
            'created_at' => 'created_at',
        ];
    }

    protected function orderByPalletDisplayName(Builder $query, string $direction): Builder
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderByRaw("COALESCE(NULLIF(pallet_name, ''), NULLIF(reference_code, ''), qr_code) {$direction}")
            ->orderBy('id');
    }

    private function orderBySentDate(Builder $query, string $direction): Builder
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $statusSlug = '(SELECT statuses.slug FROM statuses WHERE statuses.id = pallets.current_status_id LIMIT 1)';
        $hasSentDate = "pallets.last_status_changed_at IS NOT NULL AND COALESCE({$statusSlug}, '') NOT IN ('bowido-nl', 'bowido-bih', 'bowido_warehouse', 'bowido_nl')";

        return $query
            ->orderByRaw("CASE WHEN {$hasSentDate} THEN 0 ELSE 1 END ASC")
            ->orderByRaw("CASE WHEN {$hasSentDate} THEN pallets.last_status_changed_at END {$direction}")
            ->orderBy('pallets.id');
    }

    private function orderByDueDate(Builder $query, string $direction): Builder
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $statusGraceDays = '(SELECT statuses.grace_period_days FROM statuses WHERE statuses.id = pallets.current_status_id LIMIT 1)';
        $statusIsBillable = '(SELECT statuses.is_billable FROM statuses WHERE statuses.id = pallets.current_status_id LIMIT 1)';
        $statusSlug = '(SELECT statuses.slug FROM statuses WHERE statuses.id = pallets.current_status_id LIMIT 1)';
        $clientGraceDays = '(SELECT customer_details.grace_period_days FROM customer_details WHERE customer_details.user_id = pallets.user_id LIMIT 1)';
        $graceDays = "CASE WHEN COALESCE({$statusSlug}, '') IN ('bih-nl-transport', 'nl-bih-transport', 'transport', 'transport_bih_nl', 'transport_nl_bih') THEN COALESCE(pallets.grace_days, {$statusGraceDays}, 3) WHEN COALESCE({$statusIsBillable}, 0) = 1 THEN COALESCE(pallets.grace_days, {$clientGraceDays}, {$statusGraceDays}, 0) ELSE 0 END";
        $timerStartedAt = "CASE WHEN COALESCE({$statusSlug}, '') IN ('bij-de-klant', 'ophalen-klant') THEN COALESCE(pallets.customer_timer_started_at, pallets.last_status_changed_at) ELSE pallets.last_status_changed_at END";
        $hasDueDate = "{$timerStartedAt} IS NOT NULL AND ({$graceDays}) > 0";
        $dueDate = "TIMESTAMPADD(DAY, ({$graceDays}), DATE({$timerStartedAt}))";

        return $query
            ->orderByRaw("CASE WHEN {$hasDueDate} THEN 0 ELSE 1 END ASC")
            ->orderByRaw("CASE WHEN {$hasDueDate} THEN {$dueDate} END {$direction}")
            ->orderBy('pallets.id');
    }

    public function lockForUpdate(int $id): Pallet
    {
        /** @var Pallet $pallet */
        $pallet = Pallet::query()->whereKey($id)->lockForUpdate()->firstOrFail();

        return $pallet;
    }

    public function hasLinkedRecords(Pallet $pallet): bool
    {
        return $pallet->auditLogs()->exists()
            || $pallet->serviceReports()->exists()
            || $pallet->invoiceItems()->exists()
            || $pallet->deliveryLocation()->exists()
            || GhostPalletReport::query()->where('paired_pallet_id', $pallet->id)->exists();
    }

    protected function model(): Model
    {
        return new Pallet;
    }
}
