<?php

namespace App\Modules\Shared\Authorization;

use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Users\Models\User;

class PermissionChecker
{
    public function allows(User $user, ModuleKey|string $moduleKey, string $ability): bool
    {
        return $user->hasModulePermission(
            $moduleKey instanceof ModuleKey ? $moduleKey->value : $moduleKey,
            $ability,
        );
    }
}
