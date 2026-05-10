<?php

namespace App\Modules\AuditLogs\Controllers;

use App\Modules\AuditLogs\DTOs\AuditLogData;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\AuditLogs\Requests\ListAuditLogsRequest;
use App\Modules\AuditLogs\Requests\StoreAuditLogRequest;
use App\Modules\AuditLogs\Requests\UpdateAuditLogRequest;
use App\Modules\AuditLogs\Resources\AuditLogResource;
use App\Modules\AuditLogs\Services\AuditLogService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class AuditLogController extends ApiController
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function index(ListAuditLogsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        return $this->successCollection(
            $this->auditLogService->paginate(ListQueryData::fromRequest($request), $request->user()),
            AuditLogResource::class,
            __('Audit logs retrieved successfully.'),
        );
    }

    public function store(StoreAuditLogRequest $request): JsonResponse
    {
        $this->authorize('create', AuditLog::class);

        $auditLog = $this->auditLogService->create(AuditLogData::fromArray($request->validated()), $request->user());

        return $this->successItem($auditLog, AuditLogResource::class, __('Audit log created successfully.'), 201);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        $this->authorize('view', $auditLog);

        return $this->successItem(
            $this->auditLogService->find($auditLog->id, request()->user()),
            AuditLogResource::class,
            __('Audit log retrieved successfully.'),
        );
    }

    public function update(UpdateAuditLogRequest $request, AuditLog $auditLog): JsonResponse
    {
        $this->authorize('update', $auditLog);

        $updatedAuditLog = $this->auditLogService->update($auditLog, AuditLogData::fromArray([
            ...$auditLog->toArray(),
            ...$request->validated(),
        ]));

        return $this->successItem($updatedAuditLog, AuditLogResource::class, __('Audit log updated successfully.'));
    }

    public function destroy(AuditLog $auditLog): JsonResponse
    {
        $this->authorize('delete', $auditLog);

        $this->auditLogService->delete($auditLog->id, request()->user());

        return $this->success(null, __('Audit log deleted successfully.'));
    }
}
