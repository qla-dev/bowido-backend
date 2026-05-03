<?php

namespace App\Modules\GhostPalletReports\Repositories;

use App\Modules\GhostPalletReports\Models\GhostPalletReport;
use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GhostPalletReportRepository extends BaseRepository
{
    protected function allowedFilters(): array
    {
        return [
            'user_id' => 'user_id',
            'paired_pallet_id' => 'paired_pallet_id',
            'status' => 'status',
            'location' => 'location',
        ];
    }

    protected function relations(): array
    {
        return ['user.role', 'pairedPallet.currentStatus'];
    }

    protected function scopeForActor(Builder $query, ?User $actor): Builder
    {
        if ($actor?->isCustomer()) {
            $query->where('user_id', $actor->id);
        }

        return $query;
    }

    public function lockForUpdate(int $id): GhostPalletReport
    {
        /** @var GhostPalletReport $ghostPalletReport */
        $ghostPalletReport = GhostPalletReport::query()->whereKey($id)->lockForUpdate()->firstOrFail();

        return $ghostPalletReport;
    }

    protected function model(): Model
    {
        return new GhostPalletReport();
    }
}
