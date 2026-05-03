<?php

namespace App\Modules\Roles\Policies;

use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Shared\Policies\BaseModulePolicy;

class RolePolicy extends BaseModulePolicy
{
    protected function moduleKey(): ModuleKey
    {
        return ModuleKey::Roles;
    }
}
