<?php

namespace App\Modules\GhostPalletReports\Services;

use App\Modules\GhostPalletReports\DTOs\GhostPalletReportData;
use App\Modules\GhostPalletReports\Models\GhostPalletReport;
use App\Modules\GhostPalletReports\Repositories\GhostPalletReportRepository;
use App\Modules\DeliveryLocations\Services\DeliveryLocationService;
use App\Modules\PalletPhotos\Enums\PalletPhotoType;
use App\Modules\PalletPhotos\Services\PalletPhotoService;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Pallets\Repositories\PalletRepository;
use App\Modules\Shared\Enums\GhostPalletReportStatus;
use App\Modules\Shared\Services\BaseCrudService;
use App\Modules\Statuses\Models\Status;
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
        private readonly PalletPhotoService $palletPhotoService,
        private readonly DeliveryLocationService $deliveryLocationService,
    ) {
        parent::__construct($ghostPalletReportRepository);
    }

    public function create(GhostPalletReportData $data, User $actor): GhostPalletReport
    {
        $clientUserId = $actor->isCustomer() ? $actor->id : ($data->userId ?? $actor->id);

        /** @var User $user */
        $user = $this->userRepository->findOrFail($clientUserId);

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user_id' => [__('The selected user is not active.')],
            ]);
        }

        return DB::transaction(function () use ($data, $actor, $clientUserId): GhostPalletReport {
            /** @var GhostPalletReport $ghostPalletReport */
            $ghostPalletReport = $this->ghostPalletReportRepository->create([
                // The report belongs to the person who submitted it. The pallet
                // itself retains the selected customer assignment below.
                'user_id' => $actor->id,
                'status' => GhostPalletReportStatus::Open->value,
                'quantity' => $data->quantity,
                'location' => $data->location,
                'description' => $data->description,
                'notes' => $data->notes,
                'metadata' => $data->metadata,
            ]);

            $unknownStatusId = Status::query()->where('slug', 'onbekend')->value('id');
            if ($unknownStatusId === null) {
                throw ValidationException::withMessages([
                    'status' => [__('The Onbekend status is required for pallets without QR codes.')],
                ]);
            }

            $metadata = $data->metadata ?? [];
            $entries = is_array($metadata['entries'] ?? null) ? $metadata['entries'] : [];
            $firstPallet = null;
            for ($index = 0; $index < $data->quantity; $index++) {
                $entry = is_array($entries[$index] ?? null) ? $entries[$index] : [];
                /** @var Pallet $pallet */
                $pallet = Pallet::query()->create([
                    'user_id' => $clientUserId,
                    'current_status_id' => $unknownStatusId,
                    // A no-QR report has no type field. Keep that visibly
                    // incomplete instead of silently assigning "pallet".
                    'type' => 'invullen!',
                    'asset_type' => 'invullen!',
                    'pallet_name' => $this->nextNoQrPalletName(),
                    'current_location' => $entry['location'] ?? $data->location,
                    'notes' => $entry['note'] ?? $data->notes,
                    'is_ghost' => true,
                    'ghost_pallet_report_id' => $ghostPalletReport->id,
                    'last_status_changed_at' => now(),
                ]);
                $firstPallet ??= $pallet;

                if ($data->image !== null) {
                    $this->palletPhotoService->store(
                        pallet: $pallet,
                        actor: $actor,
                        image: $data->image,
                        type: PalletPhotoType::NoQrReport,
                        clientId: $clientUserId,
                    );
                }

                if (is_array($entry['delivery_location'] ?? null)) {
                    $this->deliveryLocationService->upsert($pallet, $entry['delivery_location'], $actor);
                }
            }

            // Keep the report linked to the first pallet in its batch. Every
            // additional pallet is linked through ghost_pallet_report_id.
            $ghostPalletReport->update(['paired_pallet_id' => $firstPallet?->id]);

            return $ghostPalletReport->load(['user.role', 'pairedPallet.currentStatus', 'pallets.currentStatus', 'pallets.user.customerDetail']);
        });
    }

    private function nextNoQrPalletName(): string
    {
        $lastName = Pallet::query()
            ->where('is_ghost', true)
            ->where('pallet_name', 'like', 'PWNQRC-%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('pallet_name');
        $lastNumber = $lastName !== null && preg_match('/^PWNQRC-(\d+)$/', $lastName, $matches)
            ? (int) $matches[1]
            : 0;

        return sprintf('PWNQRC-%04d', $lastNumber + 1);
    }

    public function update(GhostPalletReport $ghostPalletReport, GhostPalletReportData $data, User $actor): GhostPalletReport
    {
        return DB::transaction(function () use ($ghostPalletReport, $data, $actor): GhostPalletReport {
            $lockedGhostPalletReport = $this->ghostPalletReportRepository
                ->lockForUpdate($ghostPalletReport->id)
                ->load('pairedPallet');

            $attributes = [
                'user_id' => $lockedGhostPalletReport->user_id,
                'quantity' => $data->quantity,
                'location' => $data->location,
                'description' => $data->description,
                'notes' => $data->notes,
                'metadata' => $data->metadata,
            ];

            if (
                $lockedGhostPalletReport->paired_pallet_id !== null
                && ! $lockedGhostPalletReport->pairedPallet?->is_ghost
                && $data->pairedPalletId !== null
                && $lockedGhostPalletReport->paired_pallet_id !== $data->pairedPalletId
            ) {
                throw ValidationException::withMessages([
                    'paired_pallet_id' => [__('Paired no-QR pallet reports cannot be re-assigned to a different pallet.')],
                ]);
            }

            if (
                $data->pairedPalletId !== null
                && ($lockedGhostPalletReport->paired_pallet_id === null || $lockedGhostPalletReport->pairedPallet?->is_ghost)
            ) {
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
