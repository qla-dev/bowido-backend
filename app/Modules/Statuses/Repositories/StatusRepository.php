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

    protected function model(): Model
    {
        return new Status();
    }
}
