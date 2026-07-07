<?php

namespace App\Modules\GhostPalletReports\Repositories;

use App\Modules\GhostPalletReports\Models\GhostPalletReport;
use App\Modules\Pallets\Models\Pallet;
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
                ->orWhere('location', 'like', $like)
                ->orWhere('description', 'like', $like)
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
                ->orWhereHas('pairedPallet', function (Builder $palletQuery) use ($like): void {
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
            'location' => 'location',
            'quantity' => 'quantity',
            'reported_at' => 'reported_at',
            'paired_at' => 'paired_at',
            'client' => fn (Builder $query, string $direction) => $query->orderBy(
                User::query()
                    ->select('name')
                    ->whereColumn('users.id', 'ghost_pallet_reports.user_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'pallet' => fn (Builder $query, string $direction) => $query->orderBy(
                Pallet::query()
                    ->selectRaw("COALESCE(NULLIF(pallet_name, ''), NULLIF(reference_code, ''), qr_code)")
                    ->whereColumn('pallets.id', 'ghost_pallet_reports.paired_pallet_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'created_at' => 'created_at',
        ];
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
