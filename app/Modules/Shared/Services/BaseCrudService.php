<?php

namespace App\Modules\Shared\Services;

use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Shared\Support\OffsetPaginationResult;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class BaseCrudService
{
    public function __construct(protected BaseRepository $repository)
    {
    }

    public function paginate(ListQueryData $queryData, ?User $actor = null): OffsetPaginationResult
    {
        return $this->repository->paginate($queryData, $actor);
    }

    public function find(int $id, ?User $actor = null): Model
    {
        return $this->repository->findOrFail($id, $actor);
    }

    public function delete(int $id, ?User $actor = null): void
    {
        $model = $this->repository->findOrFail($id, $actor);
        $this->repository->delete($model);
    }
}
