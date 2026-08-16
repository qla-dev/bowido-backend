<?php

namespace App\Modules\Pallets\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdatePalletRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $palletId = $this->route('pallet')?->id;

        return [
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'current_status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
            'type' => ['sometimes', 'string', 'max:255'],
            'asset_type' => ['sometimes', 'string', 'max:255'],
            'qr_code' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('pallets', 'qr_code')->ignore($palletId)],
            'pallet_name' => ['sometimes', 'string', 'max:255'],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'current_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'is_ghost' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
