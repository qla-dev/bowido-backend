<?php

namespace App\Modules\Roles\Services;

use App\Modules\Roles\DTOs\RoleData;
use App\Modules\Roles\Models\Role;
use App\Modules\Roles\Repositories\RoleRepository;
use App\Modules\Shared\Services\BaseCrudService;
use Illuminate\Support\Facades\DB;

class RoleService extends BaseCrudService
{
    public function __construct(private readonly RoleRepository $roleRepository)
    {
        parent::__construct($roleRepository);
    }

    public function create(RoleData $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            /** @var Role $role */
            $role = $this->roleRepository->create($data->toArray());

            $this->syncIncomingPermissions($role, $data);

            return $role->load('rolePermissions.module');
        });
    }

    public function update(Role $role, RoleData $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            /** @var Role $updatedRole */
            $updatedRole = $this->roleRepository->update($role, $data->toArray());

            $this->syncIncomingPermissions($updatedRole, $data);

            return $updatedRole->load('rolePermissions.module');
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rolePermissions
     */
    private function syncRolePermissions(Role $role, array $rolePermissions): void
    {
        $rolePermissions = array_values(array_filter(
            $rolePermissions,
            fn (array $permission): bool => $this->hasAnyAbility($permission)
        ));

        $moduleIds = array_column($rolePermissions, 'module_id');

        if ($moduleIds === []) {
            $role->rolePermissions()->delete();

            return;
        }

        $role->rolePermissions()
            ->whereNotIn('module_id', $moduleIds)
            ->delete();

        foreach ($rolePermissions as $permission) {
            $role->rolePermissions()->updateOrCreate(
                ['module_id' => (int) $permission['module_id']],
                [
                    'can_list' => (bool) $permission['can_list'],
                    'can_view' => (bool) $permission['can_view'],
                    'can_create' => (bool) $permission['can_create'],
                    'can_update' => (bool) $permission['can_update'],
                    'can_delete' => (bool) $permission['can_delete'],
                ],
            );
        }
    }

    private function syncIncomingPermissions(Role $role, RoleData $data): void
    {
        if (is_array($data->rolePermissions)) {
            $this->syncRolePermissions($role, $data->rolePermissions);

            return;
        }

        if (is_array($data->moduleIds)) {
            $this->syncRolePermissions($role, array_map(
                static fn (int $moduleId): array => [
                    'module_id' => $moduleId,
                    'can_list' => true,
                    'can_view' => true,
                    'can_create' => true,
                    'can_update' => true,
                    'can_delete' => true,
                ],
                $data->moduleIds,
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $permission
     */
    private function hasAnyAbility(array $permission): bool
    {
        return (bool) (
            ($permission['can_list'] ?? false)
            || ($permission['can_view'] ?? false)
            || ($permission['can_create'] ?? false)
            || ($permission['can_update'] ?? false)
            || ($permission['can_delete'] ?? false)
        );
    }
}
