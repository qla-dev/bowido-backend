<?php

namespace App\Modules\RolePermissions\Repositories;

use App\Modules\RolePermissions\Models\RolePermission;
use App\Modules\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RolePermissionRepository extends BaseRepository
{
    protected function allowedFilters(): array
    {
        return [
            'role_id' => 'role_id',
            'module_id' => 'module_id',
            'can_list' => 'can_list',
            'can_view' => 'can_view',
            'can_create' => 'can_create',
            'can_update' => 'can_update',
            'can_delete' => 'can_delete',
        ];
    }

    protected function applyOrdering(Builder $query): Builder
    {
        return $query->orderBy('role_id')->orderBy('module_id');
    }

    protected function relations(): array
    {
        return ['role', 'module'];
    }

    protected function model(): Model
    {
        return new RolePermission();
    }
}
