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
            'is_for_repair' => 'is_for_repair',
        ];
    }

    protected function relations(): array
    {
        return ['user.customerDetail', 'currentStatus', 'deliveryLocation'];
    }

    protected function scopeForActor(Builder $query, ?User $actor): Builder
    {
        if ($actor?->isCustomer()) {
            $query
                ->where('user_id', $actor->id)
                ->where(function (Builder $customerPalletQuery): void {
                    $customerPalletQuery
                        ->where('is_ghost', true)
                        ->orWhereHas('currentStatus', fn (Builder $statusQuery) => $statusQuery->whereIn(
                            'slug',
                            PalletCustomerAssignmentRule::ALLOWED_STATUS_SLUGS,
                        ));
                });
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
            'lastUpdate' => 'last_status_changed_at',
            'last_update' => 'last_status_changed_at',
            'dueDate' => 'last_status_changed_at',
            'due_date' => 'last_status_changed_at',
            'deadline' => 'overdue_days',
            'daysOut' => 'days_at_customer',
            'days_out' => 'days_at_customer',
            'overdueDays' => 'overdue_days',
            'overdue_days' => 'overdue_days',
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
