<?php

namespace App\Modules\Users\Repositories;

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

    public function findByEmailForAuth(string $email): ?User
    {
        return User::query()
            ->with(['role', 'customerDetail'])
            ->where('email', $email)
            ->first();
    }

    protected function model(): Model
    {
        return new User();
    }
}
