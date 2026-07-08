<?php

namespace App\Modules\Pallets\Controllers;

use App\Modules\Pallets\Models\Pallet;
use App\Modules\Pallets\Services\PalletDashboardStatsService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PalletStatsController extends ApiController
{
    public function __construct(private readonly PalletDashboardStatsService $statsService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Pallet::class);

        return $this->success(
            $this->statsService->summaryFor($request->user()),
            __('Pallet dashboard statistics retrieved successfully.'),
        );
    }
}
