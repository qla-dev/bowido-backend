<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditTrailService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        int $palletId,
        ?int $madeByUserId,
        string $eventType,
        ?int $oldStatusId = null,
        ?int $newStatusId = null,
        ?int $oldClientId = null,
        ?int $newClientId = null,
        ?string $oldLocation = null,
        ?string $newLocation = null,
        ?string $oldQrCode = null,
        ?string $newQrCode = null,
        ?int $qrCodeVersion = null,
        ?string $note = null,
        array $context = [],
    ): AuditLog {
        $resolvedQrCodeVersion = $qrCodeVersion;

        if ($resolvedQrCodeVersion === null && $newQrCode !== null) {
            $resolvedQrCodeVersion = ((int) AuditLog::query()
                ->where('pallet_id', $palletId)
                ->max('qr_code_version')) + 1;
        }

        return AuditLog::query()->create([
            'pallet_id' => $palletId,
            'made_by_user_id' => $madeByUserId,
            'event_type' => $eventType,
            'note' => $note,
            'old_status_id' => $oldStatusId,
            'new_status_id' => $newStatusId,
            'old_client_id' => $oldClientId,
            'new_client_id' => $newClientId,
            'old_location' => $oldLocation,
            'new_location' => $newLocation,
            'qr_code_version' => $resolvedQrCodeVersion,
            'old_qr_code' => $oldQrCode,
            'new_qr_code' => $newQrCode,
            'context' => $context,
        ]);
    }
}