<?php

namespace App\Modules\AuditLogs\Repositories;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Enums\AuditEventType;
use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Statuses\Models\Status;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditLogRepository extends BaseRepository
{
    /**
     * @return array{total: int, status_changes: int, qr_version_changes: int}
     */
    public function summary(ListQueryData $queryData, ?User $actor = null): array
    {
        $query = $this->applySearch(
            $this->applyFilters($this->newQuery($actor), $queryData->filters),
            $queryData->search,
        );

        return [
            'total' => (clone $query)->count(),
            'status_changes' => (clone $query)
                ->where('event_type', AuditEventType::StatusChanged->value)
                ->count(),
            'qr_version_changes' => (clone $query)
                ->where('event_type', AuditEventType::QrCodeChanged->value)
                ->count(),
        ];
    }

    protected function allowedFilters(): array
    {
        return [
            'pallet_id' => 'pallet_id',
            'made_by_user_id' => 'made_by_user_id',
            'event_type' => 'event_type',
            'old_status_id' => 'old_status_id',
            'new_status_id' => 'new_status_id',
            'created_from' => fn (Builder $query, mixed $value) => $query->whereDate('created_at', '>=', $value),
            'created_to' => fn (Builder $query, mixed $value) => $query->whereDate('created_at', '<=', $value),
        ];
    }

    protected function relations(): array
    {
        return ['pallet', 'madeByUser', 'oldStatus', 'newStatus', 'oldClient.customerDetail', 'newClient.customerDetail'];
    }

    protected function scopeForActor(Builder $query, ?User $actor): Builder
    {
        if ($actor?->isCustomer()) {
            $query->whereHas('pallet', fn (Builder $builder) => $builder->where('user_id', $actor->id));
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
                ->where('event_type', 'like', $like)
                ->orWhere('note', 'like', $like)
                ->orWhere('old_location', 'like', $like)
                ->orWhere('new_location', 'like', $like)
                ->orWhere('old_qr_code', 'like', $like)
                ->orWhere('new_qr_code', 'like', $like)
                ->orWhereHas('pallet', function (Builder $palletQuery) use ($like): void {
                    $palletQuery
                        ->where('pallet_name', 'like', $like)
                        ->orWhere('reference_code', 'like', $like)
                        ->orWhere('qr_code', 'like', $like);
                })
                ->orWhereHas('madeByUser', function (Builder $userQuery) use ($like): void {
                    $userQuery
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                })
                ->orWhereHas('oldStatus', function (Builder $statusQuery) use ($like): void {
                    $statusQuery
                        ->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like);
                })
                ->orWhereHas('newStatus', function (Builder $statusQuery) use ($like): void {
                    $statusQuery
                        ->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like);
                });
        });
    }

    protected function allowedSorts(): array
    {
        return [
            'timestamp' => 'created_at',
            'created_at' => 'created_at',
            'logType' => 'event_type',
            'event_type' => 'event_type',
            'pallet' => fn (Builder $query, string $direction) => $query->orderBy(
                Pallet::query()
                    ->selectRaw("COALESCE(NULLIF(pallet_name, ''), NULLIF(reference_code, ''), qr_code)")
                    ->whereColumn('pallets.id', 'audit_logs.pallet_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'changedBy' => fn (Builder $query, string $direction) => $query->orderBy(
                User::query()
                    ->select('name')
                    ->whereColumn('users.id', 'audit_logs.made_by_user_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'old_status' => fn (Builder $query, string $direction) => $query->orderBy(
                Status::query()
                    ->select('name')
                    ->whereColumn('statuses.id', 'audit_logs.old_status_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'new_status' => fn (Builder $query, string $direction) => $query->orderBy(
                Status::query()
                    ->select('name')
                    ->whereColumn('statuses.id', 'audit_logs.new_status_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
        ];
    }

    protected function model(): Model
    {
        return new AuditLog();
    }
}
