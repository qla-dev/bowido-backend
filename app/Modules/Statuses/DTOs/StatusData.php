<?php

namespace App\Modules\Statuses\DTOs;

use App\Modules\Shared\Support\Normalizer;

readonly class StatusData
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description,
        public bool $isBillable,
        public int $gracePeriodDays,
        public float $pricePerDay,
        public bool $isActive,
        public int $sortOrder,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            name: Normalizer::name((string) ($attributes['name'] ?? $attributes['slug'])),
            slug: Normalizer::slug((string) ($attributes['slug'] ?? $attributes['name'])),
            description: $attributes['description'] ?? null,
            isBillable: (bool) ($attributes['is_billable'] ?? false),
            gracePeriodDays: (int) ($attributes['grace_period_days'] ?? 0),
            pricePerDay: round((float) ($attributes['price_per_day'] ?? 0), 2),
            isActive: (bool) ($attributes['is_active'] ?? true),
            sortOrder: (int) ($attributes['sort_order'] ?? 0),
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
            'is_billable' => $this->isBillable,
            'grace_period_days' => $this->gracePeriodDays,
            'price_per_day' => $this->pricePerDay,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
        ];
    }
}
