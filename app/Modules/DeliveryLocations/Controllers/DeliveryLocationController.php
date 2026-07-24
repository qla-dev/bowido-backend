<?php

namespace App\Modules\DeliveryLocations\Controllers;

use App\Modules\DeliveryLocations\Requests\UpsertDeliveryLocationRequest;
use App\Modules\DeliveryLocations\Resources\DeliveryLocationResource;
use App\Modules\DeliveryLocations\Services\DeliveryLocationService;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DeliveryLocationController extends ApiController
{
    public function update(
        UpsertDeliveryLocationRequest $request,
        Pallet $pallet,
        DeliveryLocationService $deliveryLocationService,
    ): JsonResponse {
        $data = $request->validated();
        $allowed = Gate::allows('updateDeliveryLocation', $pallet);

        Log::info('Pallet map location save authorization evaluated.', [
            'pallet_id' => $pallet->id,
            'actor_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role?->name,
            'allowed' => $allowed,
            'coordinates' => [
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
            ],
            'address' => array_intersect_key($data, array_flip(['street', 'house_number', 'postal_code', 'city'])),
        ]);

        abort_unless($allowed, Response::HTTP_FORBIDDEN, __('You cannot save a map location for this pallet.'));

        try {
            $location = $deliveryLocationService->upsert($pallet, $data, $request->user());
        } catch (\Throwable $exception) {
            Log::error('Pallet map location save failed.', [
                'pallet_id' => $pallet->id,
                'actor_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('Pallet map location saved.', [
            'pallet_id' => $pallet->id,
            'actor_id' => $request->user()?->id,
            'delivery_location_id' => $location->id,
            'current_location' => $pallet->fresh()->current_location,
        ]);

        return $this->successItem(
            $location,
            DeliveryLocationResource::class,
            __('Delivery location saved successfully.'),
        );
    }
}
