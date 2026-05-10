<?php

namespace App\Modules\Auth\DTOs;

use App\Modules\Shared\Support\Normalizer;

readonly class RegisterData
{
    public function __construct(
        public int $roleId,
        public string $name,
        public string $email,
        public ?string $phoneNumber,
        public string $password,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            roleId: (int) $attributes['role_id'],
            name: trim((string) $attributes['name']),
            email: strtolower(trim((string) $attributes['email'])),
            phoneNumber: Normalizer::phoneNumber($attributes['phone_number'] ?? null),
            password: (string) $attributes['password'],
        );
    }
}
