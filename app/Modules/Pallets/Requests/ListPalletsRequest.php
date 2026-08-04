<?php

namespace App\Modules\Pallets\Requests;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

class ListPalletsRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'current_status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
            'asset_type' => ['sometimes', 'string', 'max:255'],
            'qr_code' => ['sometimes', 'string', 'max:255'],
            'reference_code' => ['sometimes', 'string', 'max:255'],
            'current_location' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_ghost' => ['sometimes', 'boolean'],
            'is_for_repair' => ['sometimes', 'boolean'],
        ];
    }
}
