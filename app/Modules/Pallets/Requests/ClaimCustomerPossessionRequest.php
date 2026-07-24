<?php

namespace App\Modules\Pallets\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class ClaimCustomerPossessionRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'current_status_id' => ['required', 'integer', 'exists:statuses,id'],
            'current_location' => ['required', 'string', 'max:255'],
        ];
    }
}
