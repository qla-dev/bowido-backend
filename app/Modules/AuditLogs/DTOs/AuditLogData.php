<?php

namespace App\Modules\AuditLogs\DTOs;

readonly class AuditLogData
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function __construct(
        public int $palletId,
        public string $eventType,
        public ?string $note,
        public ?int $oldStatusId,
        public ?int $newStatusId,
        public ?int $oldClientId,
        public ?int $newClientId,
        public ?string $oldLocation,
        public ?string $newLocation,
        public ?string $oldQrCode,
        public ?string $newQrCode,
        public ?array $context,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            palletId: (int) $attributes['pallet_id'],
            eventType: (string) $attributes['event_type'],
            note: $attributes['note'] ?? null,
            oldStatusId: isset($attributes['old_status_id']) ? (int) $attributes['old_status_id'] : null,
            newStatusId: isset($attributes['new_status_id']) ? (int) $attributes['new_status_id'] : null,
            oldClientId: isset($attributes['old_client_id']) ? (int) $attributes['old_client_id'] : null,
            newClientId: isset($attributes['new_client_id']) ? (int) $attributes['new_client_id'] : null,
            oldLocation: $attributes['old_location'] ?? null,
            newLocation: $attributes['new_location'] ?? null,
            oldQrCode: $attributes['old_qr_code'] ?? null,
            newQrCode: $attributes['new_qr_code'] ?? null,
            context: isset($attributes['context']) && is_array($attributes['context']) ? $attributes['context'] : null,
        );
    }
}
