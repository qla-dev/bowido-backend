<?php

namespace App\Modules\Roles\Controllers;

use App\Modules\Roles\DTOs\RoleData;
use App\Modules\Roles\Models\Role;
use App\Modules\Roles\Requests\ListRolesRequest;
use App\Modules\Roles\Requests\StoreRoleRequest;
use App\Modules\Roles\Requests\UpdateRoleRequest;
use App\Modules\Roles\Resources\RoleResource;
use App\Modules\Roles\Services\RoleService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class RoleController extends ApiController
{
    public function __construct(private readonly RoleService $roleService)
    {
    }

    public function index(ListRolesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        return $this->successCollection(
            $this->roleService->paginate(ListQueryData::fromRequest($request), $request->user()),
            RoleResource::class,
            __('Roles retrieved successfully.'),
        );
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $role = $this->roleService->create(RoleData::fromArray($request->validated()));

        return $this->successItem($role, RoleResource::class, __('Role created successfully.'), 201);
    }

    public function show(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        return $this->successItem(
            $this->roleService->find($role->id, request()->user()),
            RoleResource::class,
            __('Role retrieved successfully.'),
        );
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $updatedRole = $this->roleService->update($role, RoleData::fromArray([
            ...$role->toArray(),
            ...$request->validated(),
        ]));

        return $this->successItem($updatedRole, RoleResource::class, __('Role updated successfully.'));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        $this->roleService->delete($role->id, request()->user());

        return $this->success(null, __('Role deleted successfully.'));
    }
}
