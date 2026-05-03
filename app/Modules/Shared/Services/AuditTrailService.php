<?php

namespace App\Modules\Shared\Services;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Shared\Enums\AuditEventType;

class AuditTrailService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        int $palletId,
        ?int $madeByUserId,
        AuditEventType|string $eventType,
        ?int $oldStatusId = null,
        ?int $newStatusId = null,
        ?int $oldClientId = null,
        ?int $newClientId = null,
        ?string $oldLocation = null,
        ?string $newLocation = null,
        ?string $oldQrCode = null,
        ?string $newQrCode = null,
        ?string $note = null,
        array $context = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'pallet_id' => $palletId,
            'made_by_user_id' => $madeByUserId,
            'event_type' => $eventType instanceof AuditEventType ? $eventType->value : $eventType,
            'note' => $note,
            'old_status_id' => $oldStatusId,
            'new_status_id' => $newStatusId,
            'old_client_id' => $oldClientId,
            'new_client_id' => $newClientId,
            'old_location' => $oldLocation,
            'new_location' => $newLocation,
            'old_qr_code' => $oldQrCode,
            'new_qr_code' => $newQrCode,
            'context' => $context,
        ]);
    }
}
