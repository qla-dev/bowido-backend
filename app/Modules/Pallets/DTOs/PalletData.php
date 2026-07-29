<?php

namespace App\Modules\Pallets\DTOs;

use App\Modules\Shared\Support\Normalizer;

readonly class PalletData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public ?int $userId,
        public int $currentStatusId,
        public string $type,
        public string $assetType,
        public string $qrCode,
        public string $palletName,
        public ?string $referenceCode,
        public ?string $currentLocation,
        public ?string $notes,
        public bool $isActive,
        public bool $isGhost,
        public ?array $metadata,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $qrCode = Normalizer::qrCode((string) ($attributes['qr_code'] ?? $attributes['pallet_name'] ?? ''));
        $palletName = isset($attributes['pallet_name']) && trim((string) $attributes['pallet_name']) !== ''
            ? Normalizer::qrCode((string) $attributes['pallet_name'])
            : $qrCode;
        $referenceCode = isset($attributes['reference_code']) && trim((string) $attributes['reference_code']) !== ''
            ? Normalizer::qrCode((string) $attributes['reference_code'])
            : null;

        return new self(
            userId: isset($attributes['user_id']) && $attributes['user_id'] !== ''
                ? (int) $attributes['user_id']
                : null,
            currentStatusId: (int) $attributes['current_status_id'],
            type: trim((string) ($attributes['type'] ?? $attributes['asset_type'] ?? 'invullen!')),
            assetType: trim((string) ($attributes['asset_type'] ?? $attributes['type'] ?? 'invullen!')),
            qrCode: $qrCode,
            palletName: $palletName,
            referenceCode: $referenceCode,
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
            'type' => $this->type,
            'asset_type' => $this->assetType,
            'qr_code' => $this->qrCode,
            'pallet_name' => $this->palletName,
            'reference_code' => $this->referenceCode,
            'current_location' => $this->currentLocation,
            'notes' => $this->notes,
            'is_active' => $this->isActive,
            'is_ghost' => $this->isGhost,
            'metadata' => $this->metadata,
        ];
    }
}
