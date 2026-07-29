<?php

namespace App\Modules\Pallets\Controllers;

use App\Modules\Pallets\DTOs\PalletData;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Pallets\Requests\ListPalletsRequest;
use App\Modules\Pallets\Requests\StorePalletRequest;
use App\Modules\Pallets\Requests\UpdatePalletRequest;
use App\Modules\Pallets\Requests\UpdatePalletLocationRequest;
use App\Modules\Pallets\Requests\UpdateClientPalletStatusRequest;
use App\Modules\Pallets\Resources\PalletResource;
use App\Modules\Pallets\Services\PalletService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class PalletController extends ApiController
{
    public function __construct(private readonly PalletService $palletService)
    {
    }

    public function index(ListPalletsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Pallet::class);

        return $this->successCollection(
            $this->palletService->paginate(ListQueryData::fromRequest($request), $request->user()),
            PalletResource::class,
            __('Pallets retrieved successfully.'),
        );
    }

    public function store(StorePalletRequest $request): JsonResponse
    {
        $this->authorize('create', Pallet::class);

        $pallet = $this->palletService->create(PalletData::fromArray($request->validated()), $request->user());

        return $this->successItem($pallet, PalletResource::class, __('Pallet created successfully.'), 201);
    }

    public function show(Pallet $pallet): JsonResponse
    {
        $this->authorize('view', $pallet);

        return $this->successItem(
            $this->palletService->find($pallet->id, request()->user()),
            PalletResource::class,
            __('Pallet retrieved successfully.'),
        );
    }

    public function update(UpdatePalletRequest $request, Pallet $pallet): JsonResponse
    {
        $this->authorize('update', $pallet);

        $updatedPallet = $this->palletService->update($pallet, PalletData::fromArray([
            ...$pallet->toArray(),
            ...$request->validated(),
        ]), $request->user());

        return $this->successItem($updatedPallet, PalletResource::class, __('Pallet updated successfully.'));
    }

    public function updateCurrentLocation(UpdatePalletLocationRequest $request, Pallet $pallet): JsonResponse
    {
        $this->authorize('updateClientTracking', $pallet);

        $updatedPallet = $this->palletService->updateCurrentLocation(
            $pallet,
            $request->validated('current_location'),
        );

        return $this->successItem($updatedPallet, PalletResource::class, __('Pallet location updated successfully.'));
    }

    public function updateClientStatus(UpdateClientPalletStatusRequest $request, Pallet $pallet): JsonResponse
    {
        $this->authorize('updateClientTracking', $pallet);

        $updatedPallet = $this->palletService->updateClientStatus(
            $pallet,
            (int) $request->validated('current_status_id'),
            $request->user(),
        );

        return $this->successItem($updatedPallet, PalletResource::class, __('Pallet status updated successfully.'));
    }

    public function destroy(Pallet $pallet): JsonResponse
    {
        $this->authorize('delete', $pallet);

        $this->palletService->delete($pallet->id, request()->user());

        return $this->success(null, __('Pallet deleted successfully.'));
    }
}
