<?php

namespace App\Modules\Modules\Policies;

use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Shared\Policies\BaseModulePolicy;

class ModulePolicy extends BaseModulePolicy
{
    protected function moduleKey(): ModuleKey
    {
        return ModuleKey::Modules;
    }
}
