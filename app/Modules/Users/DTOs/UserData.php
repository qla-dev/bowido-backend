<?php

namespace App\Modules\Users\DTOs;

use App\Modules\Shared\Support\Normalizer;

readonly class UserData
{
    /**
     * @param  array<string, mixed>|null  $customerDetails
     */
    public function __construct(
        public int $roleId,
        public string $name,
        public string $email,
        public ?string $phoneNumber,
        public ?string $password,
        public bool $isActive,
        public ?array $customerDetails,
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
            password: $attributes['password'] ?? null,
            isActive: (bool) ($attributes['is_active'] ?? true),
            customerDetails: $attributes['customer_details'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'role_id' => $this->roleId,
            'name' => $this->name,
            'email' => $this->email,
            'phone_number' => $this->phoneNumber,
            'password' => $this->password,
            'is_active' => $this->isActive,
        ], static fn ($value): bool => $value !== null);
    }
}
