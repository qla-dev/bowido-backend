<?php

namespace App\Modules\ServiceReports\Repositories;

use App\Modules\Pallets\Models\Pallet;
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
        return ['photos'];
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
                ->where('status', 'like', $like)
                ->orWhere('severity', 'like', $like)
                ->orWhere('issue_type', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('resolution_note', 'like', $like)
                ->orWhereHas('pallet', function (Builder $palletQuery) use ($like): void {
                    $palletQuery
                        ->where('pallet_name', 'like', $like)
                        ->orWhere('reference_code', 'like', $like)
                        ->orWhere('qr_code', 'like', $like);
                });
        });
    }

    protected function allowedSorts(): array
    {
        return [
            'status' => 'status',
            'severity' => 'severity',
            'issue_type' => 'issue_type',
            'pallet' => fn (Builder $query, string $direction) => $query->orderBy(
                Pallet::query()
                    ->selectRaw("COALESCE(NULLIF(pallet_name, ''), NULLIF(reference_code, ''), qr_code)")
                    ->whereColumn('pallets.id', 'service_reports.pallet_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'resolved_at' => 'resolved_at',
            'created_at' => 'created_at',
        ];
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
