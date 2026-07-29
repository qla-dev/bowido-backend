<?php

namespace App\Modules\Locations\Controllers;

use App\Modules\Locations\Exceptions\LocationProviderException;
use App\Modules\Locations\Requests\AddressSearchRequest;
use App\Modules\Locations\Services\AddressSearchService;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class AddressSearchController extends ApiController
{
    public function __invoke(AddressSearchRequest $request, AddressSearchService $addressSearchService): JsonResponse
    {
        $this->authorize('viewAny', Pallet::class);

        try {
            $results = $addressSearchService->search(
                $request->string('query')->toString(),
                $request->integer('limit', 5),
            );
        } catch (LocationProviderException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => null,
                'meta' => [],
                'errors' => [],
            ], $exception->httpStatus);
        }

        return $this->success(
            array_map(fn ($result): array => $result->toArray(), $results),
            __('Address suggestions loaded successfully.'),
        );
    }
}
