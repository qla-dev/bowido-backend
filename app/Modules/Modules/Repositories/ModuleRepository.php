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
                ->orWhere('slug', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    protected function allowedSorts(): array
    {
        return [
            'name' => 'name',
            'slug' => 'slug',
            'description' => 'description',
            'created_at' => 'created_at',
        ];
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
