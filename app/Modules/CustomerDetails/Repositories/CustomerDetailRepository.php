<?php

namespace App\Modules\CustomerDetails\Repositories;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerDetailRepository extends BaseRepository
{
    protected function allowedFilters(): array
    {
        return [
            'user_id' => 'user_id',
            'company_name' => 'company_name',
            'is_active' => 'is_active',
        ];
    }

    protected function applyOrdering(Builder $query): Builder
    {
        return $query->orderBy('company_name');
    }

    protected function relations(): array
    {
        return ['user'];
    }

    protected function scopeForActor(Builder $query, ?User $actor): Builder
    {
        if ($actor?->isCustomer()) {
            $query->where('user_id', $actor->id);
        }

        return $query;
    }

    public function findByUserId(int $userId): ?CustomerDetail
    {
        return CustomerDetail::query()->where('user_id', $userId)->first();
    }

    protected function model(): Model
    {
        return new CustomerDetail();
    }
}
