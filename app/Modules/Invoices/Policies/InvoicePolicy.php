<?php

namespace App\Modules\Invoices\Policies;

use App\Modules\Invoices\Models\Invoice;
use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Shared\Policies\BaseModulePolicy;
use App\Modules\Users\Models\User;

class InvoicePolicy extends BaseModulePolicy
{
    public function view(User $user, mixed $model): bool
    {
        /** @var Invoice $model */
        if ($user->isCustomer()) {
            return parent::view($user, $model) && $model->user_id === $user->id;
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
        return ModuleKey::Invoices;
    }
}
