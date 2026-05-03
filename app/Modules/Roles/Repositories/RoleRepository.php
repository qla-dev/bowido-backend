<?php

namespace App\Modules\Roles\Repositories;

use App\Modules\Roles\Models\Role;
use App\Modules\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RoleRepository extends BaseRepository
{
    protected function allowedFilters(): array
    {
        return [
            'name' => 'name',
            'is_active' => 'is_active',
        ];
    }

    protected function applyOrdering(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    protected function relations(): array
    {
        return ['rolePermissions.module'];
    }

    protected function model(): Model
    {
        return new Role();
    }
}
