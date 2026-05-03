<?php

namespace App\Modules\Pallets\DTOs;

use App\Modules\Shared\Support\Normalizer;

readonly class PalletData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public int $userId,
        public int $currentStatusId,
        public string $assetType,
        public string $qrCode,
        public ?string $referenceCode,
        public ?string $currentLocation,
        public ?string $notes,
        public bool $isActive,
        public bool $isGhost,
        public ?array $metadata,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            userId: (int) $attributes['user_id'],
            currentStatusId: (int) $attributes['current_status_id'],
            assetType: trim((string) ($attributes['asset_type'] ?? 'pallet')),
            qrCode: Normalizer::qrCode((string) $attributes['qr_code']),
            referenceCode: $attributes['reference_code'] ?? null,
            currentLocation: $attributes['current_location'] ?? null,
            notes: $attributes['notes'] ?? null,
            isActive: (bool) ($attributes['is_active'] ?? true),
            isGhost: (bool) ($attributes['is_ghost'] ?? false),
            metadata: isset($attributes['metadata']) && is_array($attributes['metadata']) ? $attributes['metadata'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'current_status_id' => $this->currentStatusId,
            'asset_type' => $this->assetType,
            'qr_code' => $this->qrCode,
            'reference_code' => $this->referenceCode,
            'current_location' => $this->currentLocation,
            'notes' => $this->notes,
            'is_active' => $this->isActive,
            'is_ghost' => $this->isGhost,
            'metadata' => $this->metadata,
        ];
    }
}
