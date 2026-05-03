<?php

namespace App\Modules\ServiceReports\Repositories;

use App\Modules\ServiceReports\Models\ServiceReport;
use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ServiceReportRepository extends BaseRepository
{
    protected function allowedFilters(): array
    {
        return [
            'pallet_id' => 'pallet_id',
            'status' => 'status',
            'severity' => 'severity',
            'issue_type' => 'issue_type',
            'reported_by_user_id' => 'reported_by_user_id',
            'resolved_by_user_id' => 'resolved_by_user_id',
        ];
    }

    protected function relations(): array
    {
        return ['pallet.user.role', 'pallet.currentStatus', 'reportedByUser.role', 'resolvedByUser.role'];
    }

    protected function scopeForActor(Builder $query, ?User $actor): Builder
    {
        if ($actor?->isCustomer()) {
            $query->whereHas('pallet', fn (Builder $builder) => $builder->where('user_id', $actor->id));
        }

        return $query;
    }

    public function lockForUpdate(int $id): ServiceReport
    {
        /** @var ServiceReport $serviceReport */
        $serviceReport = ServiceReport::query()->whereKey($id)->lockForUpdate()->firstOrFail();

        return $serviceReport;
    }

    protected function model(): Model
    {
        return new ServiceReport();
    }
}
