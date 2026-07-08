<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Auth\Support\AuthLoginLogger;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Str;

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

    protected function failedValidation(Validator $validator): void
    {
        AuthLoginLogger::warning('Auth login validation failed.', [
            'path' => $this->path(),
            'method' => $this->method(),
            'host' => $this->getHost(),
            'ip' => $this->ip(),
            'content_type' => $this->headers->get('content-type'),
            'accept' => $this->headers->get('accept'),
            'origin' => $this->headers->get('origin'),
            'referer' => $this->headers->get('referer'),
            'user_agent' => Str::limit((string) $this->userAgent(), 200),
            'login_type' => $this->input('login_type'),
            'has_email' => $this->filled('email'),
            'email_domain' => $this->emailDomain((string) $this->input('email')),
            'email_hash' => $this->hashedValue((string) $this->input('email')),
            'has_kvk' => $this->filled('kvk'),
            'kvk_hash' => $this->hashedValue($this->normalizedKvk((string) $this->input('kvk'))),
            'has_password' => $this->filled('password'),
            'token_only_header' => $this->headers->get('X-Trackpal-Token-Only'),
            'error_fields' => array_keys($validator->errors()->messages()),
            'errors' => $validator->errors()->messages(),
        ]);

        parent::failedValidation($validator);
    }

    private function emailDomain(string $email): ?string
    {
        $email = strtolower(trim($email));

        if (! str_contains($email, '@')) {
            return null;
        }

        return Str::afterLast($email, '@');
    }

    private function hashedValue(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : hash('sha256', strtolower($value));
    }

    private function normalizedKvk(string $kvk): string
    {
        return strtolower((string) preg_replace('/[\s.-]+/', '', trim($kvk)));
    }
}
