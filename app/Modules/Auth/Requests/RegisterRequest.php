<?php

namespace App\Modules\Auth\Requests;

use App\Modules\CustomerDetails\Support\CustomerImportExceptions;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $emailRules = ['required', 'email', 'max:255'];
        if (! CustomerImportExceptions::allowsSharedEmail($this->input('email'))) {
            $emailRules[] = 'unique:users,email';
        }

        return [
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => $emailRules,
            'phone_number' => ['nullable', 'string', 'max:255', 'unique:users,phone_number'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
