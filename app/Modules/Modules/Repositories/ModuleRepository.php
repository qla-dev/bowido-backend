<?php

namespace App\Modules\Modules\Repositories;

use App\Modules\Modules\Models\Module;
use App\Modules\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ModuleRepository extends BaseRepository
{
    protected function allowedFilters(): array
    {
        return [
            'slug' => 'slug',
            'is_active' => 'is_active',
        ];
    }

    protected function applyOrdering(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    protected function relations(): array
    {
        return ['rolePermissions.role'];
    }

    protected function model(): Model
    {
        return new Module();
    }
}
