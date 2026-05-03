<?php

namespace App\Modules\GhostPalletReports\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StoreGhostPalletReportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
