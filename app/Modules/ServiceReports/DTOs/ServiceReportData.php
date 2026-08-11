<?php

namespace App\Modules\ServiceReports\DTOs;

use Illuminate\Http\UploadedFile;

readonly class ServiceReportData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public int $palletId,
        public ?string $status,
        public ?string $severity,
        public ?string $issueType,
        public string $description,
        public ?string $resolutionNote,
        /** @var array<int, UploadedFile> */
        public array $images,
        public ?string $imagePath,
        public ?array $metadata,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            palletId: (int) $attributes['pallet_id'],
            status: $attributes['status'] ?? null,
            severity: $attributes['severity'] ?? null,
            issueType: $attributes['issue_type'] ?? null,
            description: (string) ($attributes['description'] ?? ''),
            resolutionNote: $attributes['resolution_note'] ?? null,
            images: array_values(array_filter([
                $attributes['image'] ?? null,
                ...(is_array($attributes['images'] ?? null) ? $attributes['images'] : []),
            ], static fn (mixed $image): bool => $image instanceof UploadedFile)),
            imagePath: $attributes['image_path'] ?? null,
            metadata: isset($attributes['metadata']) && is_array($attributes['metadata']) ? $attributes['metadata'] : null,
        );
    }
}
