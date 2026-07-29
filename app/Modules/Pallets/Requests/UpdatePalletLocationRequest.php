<?php

namespace App\Modules\Pallets\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class UpdatePalletLocationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'current_location' => ['required', 'string', 'max:255'],
        ];
    }
}
