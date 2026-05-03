<?php

namespace App\Modules\Roles\Services;

use App\Modules\Roles\DTOs\RoleData;
use App\Modules\Roles\Models\Role;
use App\Modules\Roles\Repositories\RoleRepository;
use App\Modules\Shared\Services\BaseCrudService;

class RoleService extends BaseCrudService
{
    public function __construct(private readonly RoleRepository $roleRepository)
    {
        parent::__construct($roleRepository);
    }

    public function create(RoleData $data): Role
    {
        /** @var Role $role */
        $role = $this->roleRepository->create($data->toArray());

        return $role->load('rolePermissions.module');
    }

    public function update(Role $role, RoleData $data): Role
    {
        /** @var Role $updatedRole */
        $updatedRole = $this->roleRepository->update($role, $data->toArray());

        return $updatedRole->load('rolePermissions.module');
    }
}
