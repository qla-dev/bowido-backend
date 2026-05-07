<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'roles', 'list');
        [$limit, $offset, $filters] = $this->listParams($request, [
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $query = Role::query()
            ->with('rolePermissions.module')
            ->when($filters['name'] ?? null, fn ($builder, $name) => $builder->where('name', 'like', '%'.$name.'%'))
            ->when(array_key_exists('is_active', $filters), fn ($builder) => $builder->where('is_active', (bool) $filters['is_active']))
            ->orderBy('name');

        [$items, $meta] = $this->paginateQuery($query, $limit, $offset);

        return $this->successCollection($items, 'role', 'Roles retrieved successfully.', $meta);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'roles', 'create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $role = Role::query()->create([
            'name' => Str::of($validated['name'])->lower()->replace(' ', '_')->value(),
            'description' => $this->normalizeText($validated['description'] ?? null),
            'is_active' => $validated['is_active'] ?? true,
        ])->load('rolePermissions.module');

        return $this->successItem($role, 'role', 'Role created successfully.', 201);
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        $this->authorizeModule($request, 'roles', 'view');

        return $this->successItem($role->load('rolePermissions.module'), 'role', 'Role retrieved successfully.');
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorizeModule($request, 'roles', 'update');

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $role->fill([
            'name' => isset($validated['name']) ? Str::of($validated['name'])->lower()->replace(' ', '_')->value() : $role->name,
            'description' => array_key_exists('description', $validated) ? $this->normalizeText($validated['description']) : $role->description,
            'is_active' => $validated['is_active'] ?? $role->is_active,
        ]);
        $role->save();

        return $this->successItem($role->fresh('rolePermissions.module'), 'role', 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorizeModule($request, 'roles', 'delete');
        $role->delete();

        return $this->success(null, 'Role deleted successfully.');
    }

    public function myRoleHelp(Request $request): JsonResponse
    {
        $roleKey = $this->normalizeRoleKey((string) $request->user()->role?->name);

        return $this->success($this->helpContent($roleKey), 'Role help content retrieved successfully.');
    }

    public function roleHelpPreview(string $role, Request $request): JsonResponse
    {
        abort_if(! $request->user()->isAdmin(), Response::HTTP_FORBIDDEN, 'Only administrators can preview role help content.');

        return $this->success($this->helpContent($this->normalizeRoleKey($role)), 'Role help preview retrieved successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function helpContent(string $roleKey): array
    {
        $content = [
            'admin' => [
                'role' => 'admin',
                'summary' => 'Administrators can manage workflows, invoices, exports, and audit trails.',
                'tips' => [
                    'Use overdue and audit filters to spot operational bottlenecks.',
                    'Only administrators can mark pallets as unknown.',
                ],
            ],
            'driver' => [
                'role' => 'driver',
                'summary' => 'Drivers can scan pallets, pair ghost pallets, and report field exceptions.',
                'tips' => [
                    'Always confirm the QR code before pairing a ghost pallet.',
                    'Use customer search instead of browsing a full customer list.',
                ],
            ],
            'warehouse_worker' => [
                'role' => 'warehouse_worker',
                'summary' => 'Warehouse workers manage inbound, outbound, and return-ready pallet flows.',
                'tips' => [
                    'Use bulk status change for truck loading and unloading.',
                    'Move damaged pallets through the dedicated service reporting flow.',
                ],
            ],
            'customer' => [
                'role' => 'customer',
                'summary' => 'Customers can review their pallets, costs, and mark pallets ready for return.',
                'tips' => [
                    'Mark pallets ready for return instead of changing statuses directly.',
                    'Preview weekly digest and current costs to track outstanding stock.',
                ],
            ],
            'service' => [
                'role' => 'service',
                'summary' => 'Service users record damage, upload repair evidence, and resolve pallets back into workflow.',
                'tips' => [
                    'A problem description and photo set are required when sending a pallet to service.',
                    'Resolve the open service report before moving the pallet back to operations.',
                ],
            ],
        ];

        return $content[$roleKey] ?? [
            'role' => $roleKey,
            'summary' => 'General operational guidance.',
            'tips' => [
                'Follow the scan workflow for daily pallet handling.',
                'Contact an administrator if you need broader access.',
            ],
        ];
    }

    private function normalizeRoleKey(string $role): string
    {
        return match (Str::of($role)->lower()->replace('-', '_')->value()) {
            'warehouse_operator' => 'warehouse_worker',
            'technician' => 'service',
            default => Str::of($role)->lower()->replace('-', '_')->value(),
        };
    }
}