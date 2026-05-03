<?php

namespace App\Modules\Statuses\Policies;

use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Shared\Policies\BaseModulePolicy;

class StatusPolicy extends BaseModulePolicy
{
    protected function moduleKey(): ModuleKey
    {
        return ModuleKey::Statuses;
    }
}
