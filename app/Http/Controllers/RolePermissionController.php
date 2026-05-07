<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class RolePermissionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'role_permissions', 'list');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        [$limit, $offset, $filters] = $this->listParams($request, [
            'role_id' => ['sometimes', 'integer', 'exists:roles,id'],
            'module_id' => ['sometimes', 'integer', 'exists:modules,id'],
            'can_list' => ['sometimes', 'boolean'],
            'can_view' => ['sometimes', 'boolean'],
            'can_create' => ['sometimes', 'boolean'],
            'can_update' => ['sometimes', 'boolean'],
            'can_delete' => ['sometimes', 'boolean'],
        ]);

        $query = RolePermission::query()
            ->with(['role', 'module'])
            ->when($filters['role_id'] ?? null, fn ($builder, $value) => $builder->where('role_id', (int) $value))
            ->when($filters['module_id'] ?? null, fn ($builder, $value) => $builder->where('module_id', (int) $value))
            ->when(array_key_exists('can_list', $filters), fn ($builder) => $builder->where('can_list', (bool) $filters['can_list']))
            ->when(array_key_exists('can_view', $filters), fn ($builder) => $builder->where('can_view', (bool) $filters['can_view']))
            ->when(array_key_exists('can_create', $filters), fn ($builder) => $builder->where('can_create', (bool) $filters['can_create']))
            ->when(array_key_exists('can_update', $filters), fn ($builder) => $builder->where('can_update', (bool) $filters['can_update']))
            ->when(array_key_exists('can_delete', $filters), fn ($builder) => $builder->where('can_delete', (bool) $filters['can_delete']))
            ->orderBy('role_id')
            ->orderBy('module_id');

        [$items, $meta] = $this->paginateQuery($query, $limit, $offset);

        return $this->successCollection($items, 'role_permission', 'Role permissions retrieved successfully.', $meta);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'role_permissions', 'create');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'module_id' => ['required', 'integer', 'exists:modules,id', Rule::unique('role_permissions')->where(
                fn ($query) => $query->where('role_id', $request->input('role_id'))
            )],
            'can_list' => ['sometimes', 'boolean'],
            'can_view' => ['sometimes', 'boolean'],
            'can_create' => ['sometimes', 'boolean'],
            'can_update' => ['sometimes', 'boolean'],
            'can_delete' => ['sometimes', 'boolean'],
        ]);

        $permission = RolePermission::query()->create([
            'role_id' => (int) $validated['role_id'],
            'module_id' => (int) $validated['module_id'],
            'can_list' => $validated['can_list'] ?? false,
            'can_view' => $validated['can_view'] ?? false,
            'can_create' => $validated['can_create'] ?? false,
            'can_update' => $validated['can_update'] ?? false,
            'can_delete' => $validated['can_delete'] ?? false,
        ])->load(['role', 'module']);

        return $this->successItem($permission, 'role_permission', 'Role permission created successfully.', 201);
    }

    public function show(Request $request, RolePermission $rolePermission): JsonResponse
    {
        $this->authorizeModule($request, 'role_permissions', 'view');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        return $this->successItem($rolePermission->load(['role', 'module']), 'role_permission', 'Role permission retrieved successfully.');
    }

    public function update(Request $request, RolePermission $rolePermission): JsonResponse
    {
        $this->authorizeModule($request, 'role_permissions', 'update');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        $validated = $request->validate([
            'role_id' => ['sometimes', 'integer', 'exists:roles,id'],
            'module_id' => [
                'sometimes',
                'integer',
                'exists:modules,id',
                Rule::unique('role_permissions')->where(function ($query) use ($request, $rolePermission): void {
                    $query->where('role_id', $request->input('role_id', $rolePermission->role_id));
                })->ignore($rolePermission->id),
            ],
            'can_list' => ['sometimes', 'boolean'],
            'can_view' => ['sometimes', 'boolean'],
            'can_create' => ['sometimes', 'boolean'],
            'can_update' => ['sometimes', 'boolean'],
            'can_delete' => ['sometimes', 'boolean'],
        ]);

        $rolePermission->fill([
            'role_id' => $validated['role_id'] ?? $rolePermission->role_id,
            'module_id' => $validated['module_id'] ?? $rolePermission->module_id,
            'can_list' => $validated['can_list'] ?? $rolePermission->can_list,
            'can_view' => $validated['can_view'] ?? $rolePermission->can_view,
            'can_create' => $validated['can_create'] ?? $rolePermission->can_create,
            'can_update' => $validated['can_update'] ?? $rolePermission->can_update,
            'can_delete' => $validated['can_delete'] ?? $rolePermission->can_delete,
        ]);
        $rolePermission->save();

        return $this->successItem($rolePermission->fresh(['role', 'module']), 'role_permission', 'Role permission updated successfully.');
    }

    public function destroy(Request $request, RolePermission $rolePermission): JsonResponse
    {
        $this->authorizeModule($request, 'role_permissions', 'delete');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');
        $rolePermission->delete();

        return $this->success(null, 'Role permission deleted successfully.');
    }
}