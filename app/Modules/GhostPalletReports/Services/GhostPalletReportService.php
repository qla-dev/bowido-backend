<?php

namespace App\Modules\GhostPalletReports\Services;

use App\Modules\GhostPalletReports\DTOs\GhostPalletReportData;
use App\Modules\GhostPalletReports\Models\GhostPalletReport;
use App\Modules\GhostPalletReports\Repositories\GhostPalletReportRepository;
use App\Modules\Pallets\Repositories\PalletRepository;
use App\Modules\Shared\Enums\GhostPalletReportStatus;
use App\Modules\Shared\Services\BaseCrudService;
use App\Modules\Users\Models\User;
use App\Modules\Users\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GhostPalletReportService extends BaseCrudService
{
    public function __construct(
        private readonly GhostPalletReportRepository $ghostPalletReportRepository,
        private readonly PalletRepository $palletRepository,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct($ghostPalletReportRepository);
    }

    public function create(GhostPalletReportData $data, User $actor): GhostPalletReport
    {
        $userId = $actor->isCustomer() ? $actor->id : ($data->userId ?? $actor->id);

        /** @var User $user */
        $user = $this->userRepository->findOrFail($userId);

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user_id' => [__('The selected user is not active.')],
            ]);
        }

        /** @var GhostPalletReport $ghostPalletReport */
        $ghostPalletReport = $this->ghostPalletReportRepository->create([
            'user_id' => $userId,
            'status' => GhostPalletReportStatus::Open->value,
            'quantity' => $data->quantity,
            'location' => $data->location,
            'description' => $data->description,
            'notes' => $data->notes,
            'metadata' => $data->metadata,
        ]);

        return $ghostPalletReport->load(['user.role', 'pairedPallet.currentStatus']);
    }

    public function update(GhostPalletReport $ghostPalletReport, GhostPalletReportData $data, User $actor): GhostPalletReport
    {
        return DB::transaction(function () use ($ghostPalletReport, $data, $actor): GhostPalletReport {
            $lockedGhostPalletReport = $this->ghostPalletReportRepository->lockForUpdate($ghostPalletReport->id);
            $userId = $data->userId ?? $lockedGhostPalletReport->user_id;
            /** @var User $user */
            $user = $this->userRepository->findOrFail($userId);

            if (! $user->is_active) {
                throw ValidationException::withMessages([
                    'user_id' => [__('The selected user is not active.')],
                ]);
            }

            $attributes = [
                'user_id' => $userId,
                'quantity' => $data->quantity,
                'location' => $data->location,
                'description' => $data->description,
                'notes' => $data->notes,
                'metadata' => $data->metadata,
            ];

            if (
                $lockedGhostPalletReport->paired_pallet_id !== null
                && $data->pairedPalletId !== null
                && $lockedGhostPalletReport->paired_pallet_id !== $data->pairedPalletId
            ) {
                throw ValidationException::withMessages([
                    'paired_pallet_id' => [__('Paired ghost pallet reports cannot be re-assigned to a different pallet.')],
                ]);
            }

            if ($data->pairedPalletId !== null && $lockedGhostPalletReport->paired_pallet_id === null) {
                $pallet = $this->palletRepository->findOrFail($data->pairedPalletId, $actor);

                $attributes['paired_pallet_id'] = $pallet->id;
                $attributes['paired_at'] = now();
                $attributes['status'] = GhostPalletReportStatus::Paired->value;
            } elseif ($data->status !== null) {
                $attributes['status'] = $data->status;
            }

            /** @var GhostPalletReport $updatedGhostPalletReport */
            $updatedGhostPalletReport = $this->ghostPalletReportRepository->update($lockedGhostPalletReport, $attributes);

            return $updatedGhostPalletReport->load(['user.role', 'pairedPallet.currentStatus']);
        });
    }
}
