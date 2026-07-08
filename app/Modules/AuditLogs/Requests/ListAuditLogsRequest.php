<?php

namespace App\Modules\AuditLogs\Requests;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

class ListAuditLogsRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'pallet_id' => ['sometimes', 'integer', 'exists:pallets,id'],
            'made_by_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'event_type' => ['sometimes', 'string', 'max:255'],
            'old_status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
            'new_status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
        ];
    }
}
