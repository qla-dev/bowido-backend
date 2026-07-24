<?php

namespace App\Modules\Pallets\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class ScanCustomerPossessionRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['qr_code' => ['required', 'string', 'min:3', 'max:255']];
    }
}
