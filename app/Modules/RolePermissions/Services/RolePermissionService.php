<?php

namespace App\Modules\RolePermissions\Services;

use App\Modules\RolePermissions\DTOs\RolePermissionData;
use App\Modules\RolePermissions\Models\RolePermission;
use App\Modules\RolePermissions\Repositories\RolePermissionRepository;
use App\Modules\Shared\Services\BaseCrudService;
use Illuminate\Support\Facades\DB;

class RolePermissionService extends BaseCrudService
{
    public function __construct(private readonly RolePermissionRepository $rolePermissionRepository)
    {
        parent::__construct($rolePermissionRepository);
    }

    public function create(RolePermissionData $data): RolePermission
    {
        return DB::transaction(function () use ($data): RolePermission {
            /** @var RolePermission $rolePermission */
            $rolePermission = $this->rolePermissionRepository->create($data->toArray());

            return $rolePermission->load(['role', 'module']);
        });
    }

    public function update(RolePermission $rolePermission, RolePermissionData $data): RolePermission
    {
        return DB::transaction(function () use ($rolePermission, $data): RolePermission {
            /** @var RolePermission $updatedRolePermission */
            $updatedRolePermission = $this->rolePermissionRepository->update($rolePermission, $data->toArray());

            return $updatedRolePermission->load(['role', 'module']);
        });
    }
}
