<?php

namespace App\Modules\Auth\DTOs;

readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
        public string $tokenName,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            email: strtolower(trim((string) $attributes['email'])),
            password: (string) $attributes['password'],
            tokenName: trim((string) ($attributes['token_name'] ?? 'api-token')),
        );
    }
}
