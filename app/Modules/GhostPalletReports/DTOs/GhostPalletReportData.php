<?php

namespace App\Modules\GhostPalletReports\DTOs;

use Illuminate\Http\UploadedFile;

readonly class GhostPalletReportData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public ?int $userId,
        public ?int $pairedPalletId,
        public ?string $status,
        public int $quantity,
        public ?string $location,
        public ?string $description,
        public ?string $notes,
        public ?array $metadata,
        /** @var array<int, UploadedFile> */
        public array $images,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            userId: isset($attributes['user_id']) ? (int) $attributes['user_id'] : null,
            pairedPalletId: isset($attributes['paired_pallet_id']) ? (int) $attributes['paired_pallet_id'] : null,
            status: $attributes['status'] ?? null,
            quantity: (int) $attributes['quantity'],
            location: $attributes['location'] ?? null,
            description: $attributes['description'] ?? null,
            notes: $attributes['notes'] ?? null,
            metadata: isset($attributes['metadata']) && is_array($attributes['metadata']) ? $attributes['metadata'] : null,
            images: array_values(array_filter([
                $attributes['image'] ?? null,
                ...(is_array($attributes['images'] ?? null) ? $attributes['images'] : []),
            ], static fn (mixed $image): bool => $image instanceof UploadedFile)),
        );
    }
}
