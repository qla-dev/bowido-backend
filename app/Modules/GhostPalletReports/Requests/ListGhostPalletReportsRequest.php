<?php

namespace App\Modules\GhostPalletReports\Requests;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

class ListGhostPalletReportsRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'paired_pallet_id' => ['sometimes', 'integer', 'exists:pallets,id'],
            'status' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
