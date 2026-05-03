<?php

namespace App\Modules\AuditLogs\Repositories;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditLogRepository extends BaseRepository
{
    protected function allowedFilters(): array
    {
        return [
            'pallet_id' => 'pallet_id',
            'made_by_user_id' => 'made_by_user_id',
            'event_type' => 'event_type',
            'old_status_id' => 'old_status_id',
            'new_status_id' => 'new_status_id',
        ];
    }

    protected function relations(): array
    {
        return ['pallet.currentStatus', 'madeByUser.role', 'oldStatus', 'newStatus', 'oldClient.role', 'newClient.role'];
    }

    protected function scopeForActor(Builder $query, ?User $actor): Builder
    {
        if ($actor?->isCustomer()) {
            $query->whereHas('pallet', fn (Builder $builder) => $builder->where('user_id', $actor->id));
        }

        return $query;
    }

    protected function model(): Model
    {
        return new AuditLog();
    }
}
