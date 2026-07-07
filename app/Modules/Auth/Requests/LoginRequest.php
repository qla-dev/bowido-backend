<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class LoginRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'login_type' => ['sometimes', 'string', 'in:user,customer'],
            'email' => ['required_without:kvk', 'required_if:login_type,user', 'nullable', 'email', 'max:255'],
            'kvk' => ['required_if:login_type,customer', 'nullable', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'token_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
