<?php

namespace App\Modules\Pallets\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StorePalletRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'current_status_id' => ['required', 'integer', 'exists:statuses,id'],
            'asset_type' => ['sometimes', 'string', 'max:255'],
            'qr_code' => ['required', 'string', 'max:255', 'unique:pallets,qr_code'],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'current_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'is_ghost' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
