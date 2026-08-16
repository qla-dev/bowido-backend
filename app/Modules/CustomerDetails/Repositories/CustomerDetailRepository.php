<?php

namespace App\Modules\CustomerDetails\Repositories;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerDetailRepository extends BaseRepository
{
    protected function newQuery(?User $actor = null): Builder
    {
        return parent::newQuery($actor)->withCount('invoices');
    }

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

    protected function applySearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        $like = "%{$search}%";

        return $query->where(function (Builder $builder) use ($like): void {
            $builder
                ->where('company_name', 'like', $like)
                ->orWhere('country', 'like', $like)
                ->orWhere('kvk', 'like', $like)
                ->orWhere('billing_email', 'like', $like)
                ->orWhere('fixed_phone', 'like', $like)
                ->orWhere('street', 'like', $like)
                ->orWhere('house_number', 'like', $like)
                ->orWhere('postal_code', 'like', $like)
                ->orWhere('city', 'like', $like)
                ->orWhere('warehouse1_street', 'like', $like)
                ->orWhere('warehouse1_house_number', 'like', $like)
                ->orWhere('warehouse1_postal_code', 'like', $like)
                ->orWhere('warehouse1_city', 'like', $like)
                ->orWhere('warehouse2_street', 'like', $like)
                ->orWhere('warehouse2_house_number', 'like', $like)
                ->orWhere('warehouse2_postal_code', 'like', $like)
                ->orWhere('warehouse2_city', 'like', $like)
                ->orWhere('vat_number', 'like', $like)
                ->orWhereHas('user', function (Builder $userQuery) use ($like): void {
                    $userQuery
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone_number', 'like', $like);
                });
        });
    }

    protected function allowedSorts(): array
    {
        return [
            'client' => 'company_name',
            'name' => 'company_name',
            'company_name' => 'company_name',
            'kvk' => 'kvk',
            'fixed_phone' => 'fixed_phone',
            'phone' => fn (Builder $query, string $direction) => $query->orderBy(
                User::query()
                    ->select('phone_number')
                    ->whereColumn('users.id', 'customer_details.user_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'address' => 'street',
            'warehouses' => 'warehouse1_street',
            'warehouse1' => 'warehouse1_street',
            'warehouse2' => 'warehouse2_street',
            'invoiceCount' => fn (Builder $query, string $direction) => $query
                ->orderBy('invoices_count', $direction)
                ->orderBy('id'),
            'country' => 'country',
            'rate' => 'default_price_per_day',
            'price_per_day' => 'default_price_per_day',
            'gracePeriod' => 'grace_period_days',
            'grace_period' => 'grace_period_days',
            'is_active' => 'is_active',
            'created_at' => 'created_at',
        ];
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
