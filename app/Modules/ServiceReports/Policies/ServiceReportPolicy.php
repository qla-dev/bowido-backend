<?php

namespace App\Modules\ServiceReports\Policies;

use App\Modules\ServiceReports\Models\ServiceReport;
use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Shared\Policies\BaseModulePolicy;
use App\Modules\Users\Models\User;

class ServiceReportPolicy extends BaseModulePolicy
{
    public function view(User $user, mixed $model): bool
    {
        /** @var ServiceReport $model */
        if ($user->isCustomer()) {
            return parent::view($user, $model) && $model->pallet?->user_id === $user->id;
        }

        return parent::view($user, $model);
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
        return ModuleKey::Services;
    }
}
