<?php

namespace App\Modules\Shared\Policies;

use App\Modules\Shared\Authorization\PermissionChecker;
use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Users\Models\User;

abstract class BaseModulePolicy
{
    public function __construct(protected PermissionChecker $permissionChecker)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->permissionChecker->allows($user, $this->moduleKey(), 'viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->permissionChecker->allows($user, $this->moduleKey(), 'view');
    }

    public function create(User $user): bool
    {
        return $this->permissionChecker->allows($user, $this->moduleKey(), 'create');
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->permissionChecker->allows($user, $this->moduleKey(), 'update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->permissionChecker->allows($user, $this->moduleKey(), 'delete');
    }

    abstract protected function moduleKey(): ModuleKey;
}
