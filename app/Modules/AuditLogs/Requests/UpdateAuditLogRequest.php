<?php

namespace App\Modules\AuditLogs\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class UpdateAuditLogRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string'],
            'context' => ['nullable', 'array'],
        ];
    }
}
