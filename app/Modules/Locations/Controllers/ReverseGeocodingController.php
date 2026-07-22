<?php

namespace App\Modules\Locations\Controllers;

use App\Modules\Locations\Exceptions\LocationProviderException;
use App\Modules\Locations\Requests\ReverseGeocodeRequest;
use App\Modules\Locations\Services\ReverseGeocodingService;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class ReverseGeocodingController extends ApiController
{
    public function __invoke(
        ReverseGeocodeRequest $request,
        ReverseGeocodingService $reverseGeocodingService,
    ): JsonResponse {
        $this->authorize('viewAny', Pallet::class);

        try {
            $result = $reverseGeocodingService->reverseGeocode(
                (float) $request->validated('latitude'),
                (float) $request->validated('longitude'),
            );
        } catch (LocationProviderException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => null,
                'meta' => [],
                'errors' => [],
            ], $exception->httpStatus);
        }

        return $this->success($result->toArray(), __('Address resolved successfully.'));
    }
}
