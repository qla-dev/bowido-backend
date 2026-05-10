<?php

namespace App\Modules\Statuses\Controllers;

use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Statuses\DTOs\StatusData;
use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Requests\ListStatusesRequest;
use App\Modules\Statuses\Requests\StoreStatusRequest;
use App\Modules\Statuses\Requests\UpdateStatusRequest;
use App\Modules\Statuses\Resources\StatusResource;
use App\Modules\Statuses\Services\StatusService;
use Illuminate\Http\JsonResponse;

class StatusController extends ApiController
{
    public function __construct(private readonly StatusService $statusService)
    {
    }

    public function index(ListStatusesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Status::class);

        return $this->successCollection(
            $this->statusService->paginate(ListQueryData::fromRequest($request), $request->user()),
            StatusResource::class,
            __('Statuses retrieved successfully.'),
        );
    }

    public function store(StoreStatusRequest $request): JsonResponse
    {
        $this->authorize('create', Status::class);

        $status = $this->statusService->create(StatusData::fromArray($request->validated()));

        return $this->successItem($status, StatusResource::class, __('Status created successfully.'), 201);
    }

    public function show(Status $status): JsonResponse
    {
        $this->authorize('view', $status);

        return $this->successItem(
            $this->statusService->find($status->id, request()->user()),
            StatusResource::class,
            __('Status retrieved successfully.'),
        );
    }

    public function update(UpdateStatusRequest $request, Status $status): JsonResponse
    {
        $this->authorize('update', $status);

        $updatedStatus = $this->statusService->update($status, StatusData::fromArray([
            ...$status->toArray(),
            ...$request->validated(),
        ]));

        return $this->successItem($updatedStatus, StatusResource::class, __('Status updated successfully.'));
    }

    public function destroy(Status $status): JsonResponse
    {
        $this->authorize('delete', $status);

        $this->statusService->delete($status->id, request()->user());

        return $this->success(null, __('Status deleted successfully.'));
    }
}
