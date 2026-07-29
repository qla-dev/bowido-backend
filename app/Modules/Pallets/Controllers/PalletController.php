<?php

namespace App\Modules\Pallets\Controllers;

use App\Modules\Pallets\DTOs\PalletData;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Pallets\Requests\ListPalletsRequest;
use App\Modules\Pallets\Requests\ClaimCustomerPossessionRequest;
use App\Modules\Pallets\Requests\ScanCustomerPossessionRequest;
use App\Modules\Pallets\Requests\StorePalletRequest;
use App\Modules\Pallets\Requests\UpdatePalletRequest;
use App\Modules\Pallets\Requests\UpdatePalletLocationRequest;
use App\Modules\Pallets\Requests\UpdateClientPalletStatusRequest;
use App\Modules\Pallets\Resources\PalletResource;
use App\Modules\Pallets\Services\PalletService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use App\Modules\Shared\Support\Normalizer;
use Illuminate\Support\Facades\Log;

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

    public function scanCustomerPossession(ScanCustomerPossessionRequest $request): JsonResponse
    {
        $this->authorize('scanCustomerPossession', Pallet::class);
        $qrCode = Normalizer::qrCode($request->validated('qr_code'));
        $pallet = Pallet::query()
            ->with(['user.customerDetail', 'currentStatus', 'deliveryLocation'])
            ->where('qr_code', $qrCode)
            ->first();

        Log::info('Customer QR scan lookup completed.', [
            'actor_id' => $request->user()->id,
            'qr_code_hash' => hash('sha256', $qrCode),
            'qr_code_length' => mb_strlen($qrCode),
            'matched_pallet_id' => $pallet?->id,
        ]);

        if ($pallet === null) {
            Log::warning('Customer QR scan lookup did not match a pallet.', [
                'actor_id' => $request->user()->id,
                'qr_code_hash' => hash('sha256', $qrCode),
                'pallets_with_qr_code' => Pallet::query()->whereNotNull('qr_code')->where('qr_code', '!=', '')->count(),
            ]);

            abort(404, __('The scanned QR code is not linked to a pallet.'));
        }

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
