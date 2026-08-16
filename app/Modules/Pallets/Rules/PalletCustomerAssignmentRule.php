<?php

namespace App\Modules\Pallets\Rules;

use App\Modules\Statuses\Models\Status;

class PalletCustomerAssignmentRule
{
    public const AT_CUSTOMER_STATUS_SLUGS = ['bij-de-klant', 'at_customer'];

    public const CUSTOMER_PICKUP_STATUS_SLUGS = ['ophalen-klant', 'pending_return'];

    public const ALLOWED_STATUS_SLUGS = [
        ...self::AT_CUSTOMER_STATUS_SLUGS,
        ...self::CUSTOMER_PICKUP_STATUS_SLUGS,
    ];

    public function statusAllowsCustomer(Status|string|null $status): bool
    {
        $slug = $status instanceof Status ? $status->slug : $status;

        return is_string($slug) && in_array($slug, self::ALLOWED_STATUS_SLUGS, true);
    }

    public function isAtCustomer(Status|string|null $status): bool
    {
        $slug = $status instanceof Status ? $status->slug : $status;

        return is_string($slug) && in_array($slug, self::AT_CUSTOMER_STATUS_SLUGS, true);
    }

    public function isCustomerPickup(Status|string|null $status): bool
    {
        $slug = $status instanceof Status ? $status->slug : $status;

        return is_string($slug) && in_array($slug, self::CUSTOMER_PICKUP_STATUS_SLUGS, true);
    }
}
