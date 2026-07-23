<?php

namespace App\Modules\Users\Repositories;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\Roles\Models\Role;
use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserRepository extends BaseRepository
{
    protected function allowedFilters(): array
    {
        return [
            'role_id' => 'role_id',
            'email' => 'email',
            'phone_number' => 'phone_number',
            'is_active' => 'is_active',
            'name' => 'name',
        ];
    }

    protected function relations(): array
    {
        return ['role', 'customerDetail'];
    }

    protected function scopeForActor(Builder $query, ?User $actor): Builder
    {
        if ($actor?->isCustomer()) {
            $query->whereKey($actor->id);
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
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone_number', 'like', $like)
                ->orWhereHas('role', function (Builder $roleQuery) use ($like): void {
                    $roleQuery
                        ->where('name', 'like', $like)
                        ->orWhere('description', 'like', $like);
                })
                ->orWhereHas('customerDetail', function (Builder $detailQuery) use ($like): void {
                    $detailQuery
                        ->where('company_name', 'like', $like)
                        ->orWhere('kvk', 'like', $like)
                        ->orWhere('billing_email', 'like', $like);
                });
        });
    }

    protected function allowedSorts(): array
    {
        return [
            'user' => 'name',
            'name' => 'name',
            'email' => 'email',
            'phone' => 'phone_number',
            'phone_number' => 'phone_number',
            'role' => fn (Builder $query, string $direction) => $query->orderBy(
                Role::query()
                    ->select('name')
                    ->whereColumn('roles.id', 'users.role_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'client' => fn (Builder $query, string $direction) => $query->orderBy(
                CustomerDetail::query()
                    ->select('company_name')
                    ->whereColumn('customer_details.user_id', 'users.id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'last_login_at' => 'last_login_at',
            'created_at' => 'created_at',
        ];
    }

    public function findByEmailForAuth(string $email): ?User
    {
        return User::query()
            ->with(['role', 'customerDetail'])
            ->where('email', $email)
            ->first();
    }

    public function findByKvkForAuth(string $kvk, ?int $customerDetailId = null): ?User
    {
        $normalizedKvk = strtolower((string) preg_replace('/[\s.-]+/', '', trim($kvk)));

        if ($normalizedKvk === '') {
            return null;
        }

        $customerDetail = CustomerDetail::query()
            ->with(['user.role', 'user.customerDetail'])
            ->where('is_active', true)
            ->when(
                $customerDetailId !== null,
                fn (Builder $query) => $query->whereKey($customerDetailId),
            )
            ->whereRaw(
                "lower(replace(replace(replace(kvk, ' ', ''), '.', ''), '-', '')) = ?",
                [$normalizedKvk],
            )
            ->first();

        return $customerDetail?->user;
    }

    protected function model(): Model
    {
        return new User();
    }
}
