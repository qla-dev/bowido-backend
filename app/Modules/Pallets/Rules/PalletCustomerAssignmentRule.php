<?php

namespace App\Modules\Pallets\Rules;

use App\Modules\Statuses\Models\Status;

class PalletCustomerAssignmentRule
{
    public const ALLOWED_STATUS_SLUGS = ['bij-de-klant', 'ophalen-klant'];

    public function statusAllowsCustomer(Status|string|null $status): bool
    {
        $slug = $status instanceof Status ? $status->slug : $status;

        return is_string($slug) && in_array($slug, self::ALLOWED_STATUS_SLUGS, true);
    }
}
