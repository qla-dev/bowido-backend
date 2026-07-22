<?php

namespace App\Modules\AuditLogs\Services;

use App\Modules\AuditLogs\DTOs\AuditLogData;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\AuditLogs\Repositories\AuditLogRepository;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Services\BaseCrudService;
use App\Modules\Users\Models\User;

class AuditLogService extends BaseCrudService
{
    public function __construct(private readonly AuditLogRepository $auditLogRepository)
    {
        parent::__construct($auditLogRepository);
    }

    public function create(AuditLogData $data, User $actor): AuditLog
    {
        /** @var AuditLog $auditLog */
        $auditLog = $this->auditLogRepository->create([
            'pallet_id' => $data->palletId,
            'made_by_user_id' => $actor->id,
            'event_type' => $data->eventType,
            'note' => $data->note,
            'old_status_id' => $data->oldStatusId,
            'new_status_id' => $data->newStatusId,
            'old_client_id' => $data->oldClientId,
            'new_client_id' => $data->newClientId,
            'old_location' => $data->oldLocation,
            'new_location' => $data->newLocation,
            'old_qr_code' => $data->oldQrCode,
            'new_qr_code' => $data->newQrCode,
            'context' => $data->context,
        ]);

        return $auditLog->load(['pallet.currentStatus', 'madeByUser.role', 'oldStatus', 'newStatus', 'oldClient.role', 'newClient.role']);
    }

    /**
     * @return array{total: int, status_changes: int, qr_version_changes: int}
     */
    public function summary(ListQueryData $queryData, ?User $actor = null): array
    {
        return $this->auditLogRepository->summary($queryData, $actor);
    }

    public function update(AuditLog $auditLog, AuditLogData $data): AuditLog
    {
        /** @var AuditLog $updatedAuditLog */
        $updatedAuditLog = $this->auditLogRepository->update($auditLog, [
            'note' => $data->note,
            'context' => $data->context,
        ]);

        return $updatedAuditLog->load(['pallet.currentStatus', 'madeByUser.role', 'oldStatus', 'newStatus', 'oldClient.role', 'newClient.role']);
    }
}
