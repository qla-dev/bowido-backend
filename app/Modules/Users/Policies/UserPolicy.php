<?php

namespace App\Modules\Users\Policies;

use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Shared\Policies\BaseModulePolicy;
use App\Modules\Users\Models\User;

class UserPolicy extends BaseModulePolicy
{
    public function distributeCredentials(User $user): bool
    {
        return $user->isAdmin();
    }

    public function viewAny(User $user): bool
    {
        if ($user->isCustomer()) {
            return false;
        }

        return parent::viewAny($user);
    }

    public function view(User $user, mixed $model): bool
    {
        /** @var User $model */
        if ($user->isCustomer()) {
            return parent::view($user, $model) && $user->id === $model->id;
        }

        return parent::view($user, $model);
    }

    public function create(User $user): bool
    {
        if ($user->isCustomer()) {
            return false;
        }

        return parent::create($user);
    }

    public function update(User $user, mixed $model): bool
    {
        if ($user->isCustomer()) {
            return false;
        }

        return parent::update($user, $model);
    }

    public function delete(User $user, mixed $model): bool
    {
        if ($user->isCustomer()) {
            return false;
        }

        return parent::delete($user, $model);
    }

    protected function moduleKey(): ModuleKey
    {
        return ModuleKey::Users;
    }
}
