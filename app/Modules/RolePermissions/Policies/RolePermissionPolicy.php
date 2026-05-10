<?php

namespace App\Modules\RolePermissions\Policies;

use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Shared\Policies\BaseModulePolicy;

class RolePermissionPolicy extends BaseModulePolicy
{
    protected function moduleKey(): ModuleKey
    {
        return ModuleKey::Roles;
    }
}
