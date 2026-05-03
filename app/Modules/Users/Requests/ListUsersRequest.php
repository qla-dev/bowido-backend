<?php

namespace App\Modules\Users\Requests;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

class ListUsersRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'role_id' => ['sometimes', 'integer', 'exists:roles,id'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone_number' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
