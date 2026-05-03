<?php

namespace App\Modules\Modules\DTOs;

use App\Modules\Shared\Support\Normalizer;
use Illuminate\Support\Str;

readonly class ModuleData
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description,
        public bool $isActive,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $name = Normalizer::name((string) ($attributes['name'] ?? $attributes['slug']));

        return new self(
            name: $name,
            slug: Normalizer::slug((string) ($attributes['slug'] ?? Str::replace(' ', '_', Str::lower($name)))),
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
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
