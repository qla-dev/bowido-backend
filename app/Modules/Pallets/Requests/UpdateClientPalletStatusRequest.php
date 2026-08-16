<?php

namespace App\Modules\Pallets\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class UpdateClientPalletStatusRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'current_status_id' => ['required', 'integer', 'exists:statuses,id'],
            'current_location' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
