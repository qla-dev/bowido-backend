<?php

namespace App\Modules\GhostPalletReports\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class UpdateGhostPalletReportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'paired_pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            'status' => ['sometimes', 'in:open,paired'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
