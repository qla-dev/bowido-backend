<?php

namespace App\Modules\Statuses\Repositories;

use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Statuses\Models\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StatusRepository extends BaseRepository
{
    protected function allowedFilters(): array
    {
        return [
            'slug' => 'slug',
            'is_billable' => 'is_billable',
            'is_active' => 'is_active',
        ];
    }

    protected function applyOrdering(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
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
            'sort_order' => 'sort_order',
            'is_billable' => 'is_billable',
            'created_at' => 'created_at',
        ];
    }

    protected function model(): Model
    {
        return new Status();
    }
}
