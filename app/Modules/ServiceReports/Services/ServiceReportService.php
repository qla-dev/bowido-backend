<?php

namespace App\Modules\ServiceReports\Services;

use App\Modules\PalletPhotos\Enums\PalletPhotoType;
use App\Modules\PalletPhotos\Services\PalletPhotoService;
use App\Modules\Pallets\Repositories\PalletRepository;
use App\Modules\ServiceReports\DTOs\ServiceReportData;
use App\Modules\ServiceReports\Models\ServiceReport;
use App\Modules\ServiceReports\Repositories\ServiceReportRepository;
use App\Modules\Shared\Enums\ServiceReportStatus;
use App\Modules\Shared\Enums\AuditEventType;
use App\Modules\Shared\Services\AuditTrailService;
use App\Modules\Shared\Services\BaseCrudService;
use App\Modules\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceReportService extends BaseCrudService
{
    public function __construct(
        private readonly ServiceReportRepository $serviceReportRepository,
        private readonly PalletRepository $palletRepository,
        private readonly PalletPhotoService $palletPhotoService,
        private readonly AuditTrailService $auditTrailService,
    ) {
        parent::__construct($serviceReportRepository);
    }

    public function create(ServiceReportData $data, User $actor): ServiceReport
    {
        $pallet = $this->palletRepository->findOrFail($data->palletId, $actor);

        if ($actor->isCustomer() && $pallet->user_id !== $actor->id) {
            throw new AuthorizationException(__('You are not allowed to create a report for this pallet.'));
        }

        return DB::transaction(function () use ($data, $actor, $pallet): ServiceReport {
            $attributes = [
                'pallet_id' => $data->palletId,
                'reported_by_user_id' => $actor->id,
                'status' => ServiceReportStatus::Open->value,
                'severity' => $data->severity,
                'issue_type' => $data->issueType,
                'description' => $data->description,
                'image_path' => $data->imagePath,
                'metadata' => $data->metadata,
            ];

            $lockedPallet = $this->palletRepository->lockForUpdate($data->palletId);
            if (! $lockedPallet->is_for_repair) {
                $this->palletRepository->update($lockedPallet, ['is_for_repair' => true]);
                $this->auditTrailService->record(
                    palletId: $lockedPallet->id,
                    madeByUserId: $actor->id,
                    eventType: AuditEventType::RepairStatusChanged,
                    note: __('Pallet marked for repair by a service report.'),
                    context: [
                        'old_is_for_repair' => false,
                        'new_is_for_repair' => true,
                        'service_report' => true,
                    ],
                );
            }

            /** @var ServiceReport $serviceReport */
            $serviceReport = $this->serviceReportRepository->create($attributes);

            if ($data->image !== null) {
                $this->palletPhotoService->store(
                    pallet: $pallet,
                    actor: $actor,
                    image: $data->image,
                    type: $data->issueType === 'service' ? PalletPhotoType::ServiceReport : PalletPhotoType::DamageReport,
                    serviceReport: $serviceReport,
                );
            }

            return $serviceReport->load(['photos', 'pallet.user.role', 'pallet.currentStatus', 'reportedByUser.role', 'resolvedByUser.role']);
        });
    }

    public function update(ServiceReport $serviceReport, ServiceReportData $data, User $actor): ServiceReport
    {
        return DB::transaction(function () use ($serviceReport, $data, $actor): ServiceReport {
            $lockedServiceReport = $this->serviceReportRepository->lockForUpdate($serviceReport->id);

            if (
                $lockedServiceReport->status === ServiceReportStatus::Resolved->value
                && $data->status === ServiceReportStatus::Open->value
            ) {
                throw ValidationException::withMessages([
                    'status' => [__('Resolved reports cannot be reopened through this endpoint.')],
                ]);
            }

            $attributes = array_filter([
                'pallet_id' => $data->palletId,
                'severity' => $data->severity,
                'issue_type' => $data->issueType,
                'description' => $data->description,
                'metadata' => $data->metadata,
            ], static fn ($value): bool => $value !== null && $value !== '');

            if ($data->image !== null) {
                $this->palletPhotoService->store(
                    pallet: $lockedServiceReport->pallet,
                    actor: $actor,
                    image: $data->image,
                    type: $data->issueType === 'service' ? PalletPhotoType::ServiceReport : PalletPhotoType::DamageReport,
                    serviceReport: $lockedServiceReport,
                );
            } elseif ($data->imagePath !== null) {
                $attributes['image_path'] = $data->imagePath;
            }

            if ($data->status === ServiceReportStatus::Resolved->value && $lockedServiceReport->status !== ServiceReportStatus::Resolved->value) {
                $attributes['status'] = ServiceReportStatus::Resolved->value;
                $attributes['resolved_by_user_id'] = $actor->id;
                $attributes['resolved_at'] = now();
                $attributes['resolution_note'] = $data->resolutionNote;
            } elseif ($data->status !== null) {
                $attributes['status'] = $data->status;
            }

            /** @var ServiceReport $updatedServiceReport */
            $updatedServiceReport = $this->serviceReportRepository->update($lockedServiceReport, $attributes);

            return $updatedServiceReport->load(['photos', 'pallet.user.role', 'pallet.currentStatus', 'reportedByUser.role', 'resolvedByUser.role']);
        });
    }
}
