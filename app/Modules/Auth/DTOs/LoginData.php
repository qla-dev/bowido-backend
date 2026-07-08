<?php

namespace App\Modules\Auth\DTOs;

readonly class LoginData
{
    public function __construct(
        public string $loginType,
        public ?string $email,
        public ?string $kvk,
        public string $password,
        public string $tokenName,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $loginType = strtolower(trim((string) ($attributes['login_type'] ?? '')));
        $loginType = $loginType === 'customer' ? 'customer' : 'user';

        return new self(
            loginType: $loginType,
            email: isset($attributes['email']) ? strtolower(trim((string) $attributes['email'])) : null,
            kvk: isset($attributes['kvk']) ? trim((string) $attributes['kvk']) : null,
            password: (string) $attributes['password'],
            tokenName: trim((string) ($attributes['token_name'] ?? 'api-token')),
        );
    }
}
