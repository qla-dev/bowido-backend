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
    public function recordCreation(Pallet $pallet, array $attributes, User $actor): void
    {
        $this->auditTrailService->record(
            palletId: $pallet->id,
            madeByUserId: $actor->id,
            eventType: AuditEventType::Created,
            newStatusId: $pallet->current_status_id,
            newClientId: $pallet->user_id,
            newLocation: $pallet->current_location,
            newQrCode: $pallet->qr_code,
            note: $attributes['notes'] ?? null,
            context: ['asset_type' => $pallet->asset_type],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordMutations(Pallet $pallet, array $originalAttributes, array $attributes, User $actor): void
    {
        if (
            array_key_exists('current_status_id', $attributes)
            && (int) $originalAttributes['current_status_id'] !== (int) $attributes['current_status_id']
        ) {
            $this->auditTrailService->record(
                palletId: $pallet->id,
                madeByUserId: $actor->id,
                eventType: AuditEventType::StatusChanged,
                oldStatusId: (int) $originalAttributes['current_status_id'],
                newStatusId: (int) $attributes['current_status_id'],
                note: $attributes['notes'] ?? null,
            );
        }

        if (
            array_key_exists('user_id', $attributes)
            && $this->nullableInt($originalAttributes['user_id']) !== $this->nullableInt($attributes['user_id'])
        ) {
            $this->auditTrailService->record(
                palletId: $pallet->id,
                madeByUserId: $actor->id,
                eventType: AuditEventType::ClientChanged,
                oldClientId: $this->nullableInt($originalAttributes['user_id']),
                newClientId: $this->nullableInt($attributes['user_id']),
                note: $attributes['notes'] ?? null,
            );
        }

        if (
            array_key_exists('current_location', $attributes)
            && (string) ($originalAttributes['current_location'] ?? '') !== (string) ($attributes['current_location'] ?? '')
        ) {
            $this->auditTrailService->record(
                palletId: $pallet->id,
                madeByUserId: $actor->id,
                eventType: AuditEventType::LocationChanged,
                oldLocation: $originalAttributes['current_location'],
                newLocation: $attributes['current_location'],
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
