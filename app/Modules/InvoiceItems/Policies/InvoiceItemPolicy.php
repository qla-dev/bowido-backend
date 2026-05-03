<?php

namespace App\Modules\InvoiceItems\Policies;

use App\Modules\InvoiceItems\Models\InvoiceItem;
use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Shared\Policies\BaseModulePolicy;
use App\Modules\Users\Models\User;

class InvoiceItemPolicy extends BaseModulePolicy
{
    public function view(User $user, mixed $model): bool
    {
        /** @var InvoiceItem $model */
        if ($user->isCustomer()) {
            return parent::view($user, $model) && $model->invoice?->user_id === $user->id;
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
        return ModuleKey::InvoiceItems;
    }
}
