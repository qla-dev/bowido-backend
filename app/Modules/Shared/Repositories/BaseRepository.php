<?php

namespace App\Modules\Shared\Repositories;

use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Support\OffsetPaginationResult;
use App\Modules\Users\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    public function paginate(ListQueryData $queryData, ?User $actor = null): OffsetPaginationResult
    {
        $query = $this->applyFilters($this->newQuery($actor), $queryData->filters);
        $total = (clone $query)->count();
        $items = $this->applyOrdering($query)
            ->offset($queryData->offset)
            ->limit($queryData->limit)
            ->get();

        return new OffsetPaginationResult($items, $total, $queryData->limit, $queryData->offset);
    }

    public function findOrFail(int $id, ?User $actor = null): Model
    {
        return $this->newQuery($actor)->whereKey($id)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Model
    {
        /** @var Model $model */
        $model = $this->model()->newQuery()->create($attributes);

        return $model->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes);
        $model->save();

        return $model->refresh();
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }

    protected function newQuery(?User $actor = null): Builder
    {
        $query = $this->model()->newQuery()->with($this->relations());

        return $this->scopeForActor($query, $actor);
    }

    protected function scopeForActor(Builder $query, ?User $actor): Builder
    {
        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        foreach ($filters as $key => $value) {
            if (! array_key_exists($key, $this->allowedFilters())) {
                continue;
            }

            $filter = $this->allowedFilters()[$key];

            if ($filter instanceof Closure) {
                $filter($query, $value);

                continue;
            }

            $column = is_string($filter) ? $filter : $key;

            if (is_array($value)) {
                $query->whereIn($column, $value);

                continue;
            }

            $query->where($column, $value);
        }

        return $query;
    }

    protected function applyOrdering(Builder $query): Builder
    {
        return $query->latest($this->defaultOrderColumn());
    }

    protected function defaultOrderColumn(): string
    {
        return 'id';
    }

    /**
     * @return array<int, string>
     */
    protected function relations(): array
    {
        return [];
    }

    /**
     * @return array<string, string|Closure>
     */
    abstract protected function allowedFilters(): array;

    abstract protected function model(): Model;
}
