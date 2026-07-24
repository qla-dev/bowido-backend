<?php

namespace App\Modules\Pallets\Controllers;

use App\Modules\Pallets\DTOs\PalletData;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Pallets\Requests\ListPalletsRequest;
use App\Modules\Pallets\Requests\ClaimCustomerPossessionRequest;
use App\Modules\Pallets\Requests\ScanCustomerPossessionRequest;
use App\Modules\Pallets\Requests\StorePalletRequest;
use App\Modules\Pallets\Requests\UpdatePalletRequest;
use App\Modules\Pallets\Resources\PalletResource;
use App\Modules\Pallets\Services\PalletService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use App\Modules\Shared\Support\Normalizer;

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

    public function destroy(Pallet $pallet): JsonResponse
    {
        $this->authorize('delete', $pallet);

        $this->palletService->delete($pallet->id, request()->user());

        return $this->success(null, __('Pallet deleted successfully.'));
    }

    public function scanCustomerPossession(ScanCustomerPossessionRequest $request): JsonResponse
    {
        $this->authorize('scanCustomerPossession', Pallet::class);
        $qrCode = Normalizer::qrCode($request->validated('qr_code'));
        $pallet = Pallet::query()
            ->with(['user.customerDetail', 'currentStatus', 'deliveryLocation'])
            ->where('qr_code', $qrCode)
            ->firstOrFail();

        return $this->successItem($pallet, PalletResource::class, __('Pallet scanned successfully.'));
    }

    public function claimCustomerPossession(
        ClaimCustomerPossessionRequest $request,
        Pallet $pallet,
    ): JsonResponse {
        $this->authorize('claimCustomerPossession', $pallet);
        $data = $request->validated();
        $updatedPallet = $this->palletService->claimCustomerPossession(
            $pallet,
            $request->user(),
            (int) $data['current_status_id'],
            $data['current_location'],
        );

        return $this->successItem($updatedPallet, PalletResource::class, __('Pallet assigned to your possession.'));
    }
}
