<?php

namespace App\Modules\Pallets\Services;

use App\Modules\Pallets\DTOs\PalletData;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Pallets\Repositories\PalletRepository;
use App\Modules\Shared\Services\BaseCrudService;
use App\Modules\Shared\Services\TrackableAssetService;
use App\Modules\Statuses\Repositories\StatusRepository;
use App\Modules\Users\Models\User;
use App\Modules\Users\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PalletService extends BaseCrudService
{
    public function __construct(
        private readonly PalletRepository $palletRepository,
        private readonly UserRepository $userRepository,
        private readonly StatusRepository $statusRepository,
        private readonly TrackableAssetService $trackableAssetService,
    ) {
        parent::__construct($palletRepository);
    }

    public function create(PalletData $data, User $actor): Pallet
    {
        $this->ensureDependenciesAreActive($data);

        return DB::transaction(function () use ($data, $actor): Pallet {
            $attributes = $data->toArray();
            $attributes['last_status_changed_at'] = now();

            /** @var Pallet $pallet */
            $pallet = $this->palletRepository->create($attributes);

            $this->trackableAssetService->recordCreation($pallet, $attributes, $actor);

            return $pallet->load(['user.role', 'user.customerDetail', 'currentStatus']);
        });
    }

    public function update(Pallet $pallet, PalletData $data, User $actor): Pallet
    {
        $this->ensureDependenciesAreActive($data);

        return DB::transaction(function () use ($pallet, $data, $actor): Pallet {
            $lockedPallet = $this->palletRepository->lockForUpdate($pallet->id);
            $originalAttributes = $lockedPallet->only(['user_id', 'current_status_id', 'current_location', 'qr_code']);
            $attributes = $data->toArray();

            if ((int) $originalAttributes['current_status_id'] !== $data->currentStatusId) {
                $attributes['last_status_changed_at'] = now();
            }

            /** @var Pallet $updatedPallet */
            $updatedPallet = $this->palletRepository->update($lockedPallet, $attributes);

            $this->trackableAssetService->recordMutations(
                pallet: $updatedPallet,
                originalAttributes: $originalAttributes,
                attributes: $attributes,
                actor: $actor,
            );

            return $updatedPallet->load(['user.role', 'user.customerDetail', 'currentStatus']);
        });
    }

    public function delete(int $id, ?User $actor = null): void
    {
        /** @var Pallet $pallet */
        $pallet = $this->palletRepository->findOrFail($id, $actor);

        if ($this->palletRepository->hasLinkedRecords($pallet)) {
            throw ValidationException::withMessages([
                'pallet' => [__('Pallets with linked history, reports, ghost pairings, or invoice items cannot be deleted.')],
            ]);
        }

        $this->palletRepository->delete($pallet);
    }

    private function ensureDependenciesAreActive(PalletData $data): void
    {
        /** @var User $user */
        $user = $this->userRepository->findOrFail($data->userId);
        $status = $this->statusRepository->findOrFail($data->currentStatusId);

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user_id' => [__('The selected user is not active.')],
            ]);
        }

        if (! $status->is_active) {
            throw ValidationException::withMessages([
                'current_status_id' => [__('The selected status is not active.')],
            ]);
        }
    }
}
