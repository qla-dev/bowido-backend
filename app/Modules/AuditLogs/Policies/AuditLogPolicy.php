<?php

namespace App\Modules\AuditLogs\Policies;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Shared\Policies\BaseModulePolicy;
use App\Modules\Users\Models\User;

class AuditLogPolicy extends BaseModulePolicy
{
    public function view(User $user, mixed $model): bool
    {
        /** @var AuditLog $model */
        if ($user->isCustomer()) {
            return parent::view($user, $model) && $model->pallet?->user_id === $user->id;
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
        return $user->isAdmin() && parent::update($user, $model);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $user->isAdmin() && parent::delete($user, $model);
    }

    protected function moduleKey(): ModuleKey
    {
        return ModuleKey::AuditLogs;
    }
}
