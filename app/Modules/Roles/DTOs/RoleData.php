<?php

namespace App\Modules\Roles\DTOs;

use Illuminate\Support\Str;

readonly class RoleData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public bool $isActive,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            name: Str::of((string) $attributes['name'])->squish()->lower()->value(),
            description: $attributes['description'] ?? null,
            isActive: (bool) ($attributes['is_active'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
