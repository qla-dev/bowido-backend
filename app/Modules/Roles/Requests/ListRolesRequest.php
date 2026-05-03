<?php

namespace App\Modules\Roles\Requests;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

class ListRolesRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
