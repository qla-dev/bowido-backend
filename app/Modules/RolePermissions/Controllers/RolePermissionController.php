<?php

namespace App\Modules\RolePermissions\Controllers;

use App\Modules\RolePermissions\DTOs\RolePermissionData;
use App\Modules\RolePermissions\Models\RolePermission;
use App\Modules\RolePermissions\Requests\ListRolePermissionsRequest;
use App\Modules\RolePermissions\Requests\StoreRolePermissionRequest;
use App\Modules\RolePermissions\Requests\UpdateRolePermissionRequest;
use App\Modules\RolePermissions\Resources\RolePermissionResource;
use App\Modules\RolePermissions\Services\RolePermissionService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class RolePermissionController extends ApiController
{
    public function __construct(private readonly RolePermissionService $rolePermissionService)
    {
    }

    public function index(ListRolePermissionsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', RolePermission::class);

        return $this->successCollection(
            $this->rolePermissionService->paginate(ListQueryData::fromRequest($request), $request->user()),
            RolePermissionResource::class,
            'Role permissions retrieved successfully.',
        );
    }

    public function store(StoreRolePermissionRequest $request): JsonResponse
    {
        $this->authorize('create', RolePermission::class);

        $rolePermission = $this->rolePermissionService->create(RolePermissionData::fromArray($request->validated()));

        return $this->successItem($rolePermission, RolePermissionResource::class, 'Role permission created successfully.', 201);
    }

    public function show(RolePermission $rolePermission): JsonResponse
    {
        $this->authorize('view', $rolePermission);

        return $this->successItem(
            $this->rolePermissionService->find($rolePermission->id, request()->user()),
            RolePermissionResource::class,
            'Role permission retrieved successfully.',
        );
    }

    public function update(UpdateRolePermissionRequest $request, RolePermission $rolePermission): JsonResponse
    {
        $this->authorize('update', $rolePermission);

        $updatedRolePermission = $this->rolePermissionService->update($rolePermission, RolePermissionData::fromArray([
            ...$rolePermission->toArray(),
            ...$request->validated(),
        ]));

        return $this->successItem($updatedRolePermission, RolePermissionResource::class, 'Role permission updated successfully.');
    }

    public function destroy(RolePermission $rolePermission): JsonResponse
    {
        $this->authorize('delete', $rolePermission);

        $this->rolePermissionService->delete($rolePermission->id, request()->user());

        return $this->success(null, 'Role permission deleted successfully.');
    }
}
