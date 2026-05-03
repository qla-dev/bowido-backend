<?php

namespace App\Modules\Statuses\Requests;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

class ListStatusesRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'slug' => ['sometimes', 'string', 'max:255'],
            'is_billable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
