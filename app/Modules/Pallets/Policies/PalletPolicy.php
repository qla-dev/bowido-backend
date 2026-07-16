<?php

namespace App\Modules\Pallets\Policies;

use App\Modules\Pallets\Models\Pallet;
use App\Modules\Pallets\Rules\PalletCustomerAssignmentRule;
use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Shared\Policies\BaseModulePolicy;
use App\Modules\Users\Models\User;

class PalletPolicy extends BaseModulePolicy
{
    public function view(User $user, mixed $model): bool
    {
        /** @var Pallet $model */
        if ($user->isCustomer()) {
            return parent::view($user, $model)
                && $model->user_id === $user->id
                && app(PalletCustomerAssignmentRule::class)->statusAllowsCustomer($model->currentStatus);
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
        return ModuleKey::Pallets;
    }
}
