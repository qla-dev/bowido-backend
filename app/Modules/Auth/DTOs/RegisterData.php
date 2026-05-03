<?php

namespace App\Modules\Auth\DTOs;

use App\Modules\Shared\Support\Normalizer;

readonly class RegisterData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phoneNumber,
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
            name: trim((string) $attributes['name']),
            email: strtolower(trim((string) $attributes['email'])),
            phoneNumber: Normalizer::phoneNumber($attributes['phone_number'] ?? null),
            password: (string) $attributes['password'],
            tokenName: trim((string) ($attributes['token_name'] ?? 'api-token')),
        );
    }
}
