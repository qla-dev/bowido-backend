<?php

namespace App\Modules\PalletPhotos\Requests;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

class ListCustomerPalletPhotosRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [];
    }
}
