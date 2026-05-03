<?php

namespace App\Modules\ServiceReports\Requests;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

class ListServiceReportsRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'pallet_id' => ['sometimes', 'integer', 'exists:pallets,id'],
            'status' => ['sometimes', 'string', 'max:255'],
            'severity' => ['sometimes', 'string', 'max:255'],
            'issue_type' => ['sometimes', 'string', 'max:255'],
            'reported_by_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'resolved_by_user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }
}
