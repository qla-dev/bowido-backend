<?php

namespace App\Modules\AuditLogs\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StoreAuditLogRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'pallet_id' => ['required', 'integer', 'exists:pallets,id'],
            'event_type' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'old_status_id' => ['nullable', 'integer', 'exists:statuses,id'],
            'new_status_id' => ['nullable', 'integer', 'exists:statuses,id'],
            'old_client_id' => ['nullable', 'integer', 'exists:users,id'],
            'new_client_id' => ['nullable', 'integer', 'exists:users,id'],
            'old_location' => ['nullable', 'string', 'max:255'],
            'new_location' => ['nullable', 'string', 'max:255'],
            'old_qr_code' => ['nullable', 'string', 'max:255'],
            'new_qr_code' => ['nullable', 'string', 'max:255'],
            'context' => ['nullable', 'array'],
        ];
    }
}
