<?php

namespace App\Modules\Shared\Services;

use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\Enums\AuditEventType;
use App\Modules\Users\Models\User;

class TrackableAssetService
{
    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordMutations(Pallet $pallet, array $originalAttributes, array $attributes, User $actor): void
    {
        $statusChanged = array_key_exists('current_status_id', $attributes)
            && (int) $originalAttributes['current_status_id'] !== (int) $attributes['current_status_id'];

        if (
            $statusChanged
        ) {
            $this->auditTrailService->record(
                palletId: $pallet->id,
                madeByUserId: $actor->id,
                eventType: AuditEventType::StatusChanged,
                oldStatusId: (int) $originalAttributes['current_status_id'],
                newStatusId: (int) $attributes['current_status_id'],
                oldClientId: $this->nullableInt($originalAttributes['user_id']),
                newClientId: $this->nullableInt($attributes['user_id']),
                oldLocation: $originalAttributes['current_location'] ?? null,
                newLocation: $attributes['current_location'] ?? null,
                note: $attributes['notes'] ?? null,
            );
        }

        if (
            ! $statusChanged
            && array_key_exists('current_location', $attributes)
            && (string) ($originalAttributes['current_location'] ?? '') !== (string) ($attributes['current_location'] ?? '')
        ) {
            $this->auditTrailService->record(
                palletId: $pallet->id,
                madeByUserId: $actor->id,
                eventType: AuditEventType::LocationChanged,
                oldStatusId: (int) $originalAttributes['current_status_id'],
                newStatusId: (int) $originalAttributes['current_status_id'],
                oldClientId: $this->nullableInt($originalAttributes['user_id']),
                newClientId: $this->nullableInt($originalAttributes['user_id']),
                oldLocation: $originalAttributes['current_location'] ?? null,
                newLocation: $attributes['current_location'] ?? null,
                note: $attributes['notes'] ?? null,
            );
        }

        if (
            array_key_exists('qr_code', $attributes)
            && (string) $originalAttributes['qr_code'] !== (string) $attributes['qr_code']
        ) {
            $this->auditTrailService->record(
                palletId: $pallet->id,
                madeByUserId: $actor->id,
                eventType: AuditEventType::QrCodeChanged,
                oldQrCode: $originalAttributes['qr_code'],
                newQrCode: $attributes['qr_code'],
                note: $attributes['notes'] ?? null,
            );
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
