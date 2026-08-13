<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Auth\Support\AuthRegistrationLogger;
use App\Modules\CustomerDetails\Support\CustomerImportExceptions;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Str;
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
            'password' => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $traceId = (string) ($this->attributes->get('auth_registration_trace_id') ?: Str::uuid());
        $this->attributes->set('auth_registration_trace_id', $traceId);

        AuthRegistrationLogger::warning('Auth registration validation failed.', [
            'trace_id' => $traceId,
            'registration_type' => 'staff_user',
            'path' => $this->path(),
            'method' => $this->method(),
            'ip' => $this->ip(),
            'email_domain' => $this->emailDomain((string) $this->input('email')),
            'email_hash' => $this->hashedValue((string) $this->input('email')),
            'has_phone_number' => $this->filled('phone_number'),
            'role_id' => $this->input('role_id'),
            'has_password' => $this->filled('password'),
            'has_password_confirmation' => $this->filled('password_confirmation'),
            'error_fields' => array_keys($validator->errors()->messages()),
            'errors' => $validator->errors()->messages(),
        ]);

        parent::failedValidation($validator);
    }

    private function emailDomain(string $email): ?string
    {
        $email = strtolower(trim($email));

        return str_contains($email, '@') ? Str::afterLast($email, '@') : null;
    }

    private function hashedValue(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : hash('sha256', strtolower($value));
    }
}
