<?php

namespace App\Modules\Pallets\Repositories;

use App\Modules\GhostPalletReports\Models\GhostPalletReport;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\Repositories\BaseRepository;
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
        ];
    }

    protected function relations(): array
    {
        return ['user.role', 'user.customerDetail', 'currentStatus'];
    }

    protected function scopeForActor(Builder $query, ?User $actor): Builder
    {
        if ($actor?->isCustomer()) {
            $query->where('user_id', $actor->id);
        }

        return $query;
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
            || GhostPalletReport::query()->where('paired_pallet_id', $pallet->id)->exists();
    }

    protected function model(): Model
    {
        return new Pallet();
    }
}
