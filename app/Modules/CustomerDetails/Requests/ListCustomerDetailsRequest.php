<?php

namespace App\Modules\CustomerDetails\Requests;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

class ListCustomerDetailsRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
