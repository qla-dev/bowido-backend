<?php

namespace App\Modules\Modules\Requests;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

class ListModulesRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'slug' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
