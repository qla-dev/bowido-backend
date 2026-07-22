<?php

namespace App\Modules\DeliveryLocations\Controllers;

use App\Modules\DeliveryLocations\Requests\UpsertDeliveryLocationRequest;
use App\Modules\DeliveryLocations\Resources\DeliveryLocationResource;
use App\Modules\DeliveryLocations\Services\DeliveryLocationService;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class DeliveryLocationController extends ApiController
{
    public function update(
        UpsertDeliveryLocationRequest $request,
        Pallet $pallet,
        DeliveryLocationService $deliveryLocationService,
    ): JsonResponse {
        $this->authorize('update', $pallet);

        $location = $deliveryLocationService->upsert($pallet, $request->validated(), $request->user());

        return $this->successItem(
            $location,
            DeliveryLocationResource::class,
            __('Delivery location saved successfully.'),
        );
    }
}
