<?php

namespace App\Modules\PalletPhotos\Controllers;

use App\Modules\PalletPhotos\Requests\StoreDeliveryPhotoRequest;
use App\Modules\PalletPhotos\Resources\PalletPhotoResource;
use App\Modules\PalletPhotos\Services\DeliveryPhotoService;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class DeliveryPhotoController extends ApiController
{
    public function __construct(private readonly DeliveryPhotoService $deliveryPhotos) {}

    public function store(StoreDeliveryPhotoRequest $request, Pallet $pallet): JsonResponse
    {
        $this->authorize('update', $pallet);

        $photo = $this->deliveryPhotos->store($pallet, $request->user(), $request->file('photo'));

        return $this->successItem($photo, PalletPhotoResource::class, __('Delivery photo uploaded successfully.'), 201);
    }
}
