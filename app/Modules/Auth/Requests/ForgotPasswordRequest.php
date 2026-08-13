<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class ForgotPasswordRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
