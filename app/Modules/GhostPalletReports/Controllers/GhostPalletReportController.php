<?php

namespace App\Modules\GhostPalletReports\Controllers;

use App\Modules\GhostPalletReports\DTOs\GhostPalletReportData;
use App\Modules\GhostPalletReports\Models\GhostPalletReport;
use App\Modules\GhostPalletReports\Requests\ListGhostPalletReportsRequest;
use App\Modules\GhostPalletReports\Requests\StoreGhostPalletReportRequest;
use App\Modules\GhostPalletReports\Requests\UpdateGhostPalletReportRequest;
use App\Modules\GhostPalletReports\Resources\GhostPalletReportResource;
use App\Modules\GhostPalletReports\Services\GhostPalletReportService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class GhostPalletReportController extends ApiController
{
    public function __construct(private readonly GhostPalletReportService $ghostPalletReportService)
    {
    }

    public function index(ListGhostPalletReportsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', GhostPalletReport::class);

        return $this->successCollection(
            $this->ghostPalletReportService->paginate(ListQueryData::fromRequest($request), $request->user()),
            GhostPalletReportResource::class,
            __('Ghost pallet reports retrieved successfully.'),
        );
    }

    public function store(StoreGhostPalletReportRequest $request): JsonResponse
    {
        $this->authorize('create', GhostPalletReport::class);

        $ghostPalletReport = $this->ghostPalletReportService->create(
            GhostPalletReportData::fromArray($request->validated()),
            $request->user(),
        );

        return $this->successItem($ghostPalletReport, GhostPalletReportResource::class, __('Ghost pallet report created successfully.'), 201);
    }

    public function show(GhostPalletReport $ghostPalletReport): JsonResponse
    {
        $this->authorize('view', $ghostPalletReport);

        return $this->successItem(
            $this->ghostPalletReportService->find($ghostPalletReport->id, request()->user()),
            GhostPalletReportResource::class,
            __('Ghost pallet report retrieved successfully.'),
        );
    }

    public function update(UpdateGhostPalletReportRequest $request, GhostPalletReport $ghostPalletReport): JsonResponse
    {
        $this->authorize('update', $ghostPalletReport);

        $updatedGhostPalletReport = $this->ghostPalletReportService->update(
            $ghostPalletReport,
            GhostPalletReportData::fromArray([
                ...$ghostPalletReport->toArray(),
                ...$request->validated(),
            ]),
            $request->user(),
        );

        return $this->successItem($updatedGhostPalletReport, GhostPalletReportResource::class, __('Ghost pallet report updated successfully.'));
    }

    public function destroy(GhostPalletReport $ghostPalletReport): JsonResponse
    {
        $this->authorize('delete', $ghostPalletReport);

        $this->ghostPalletReportService->delete($ghostPalletReport->id, request()->user());

        return $this->success(null, __('Ghost pallet report deleted successfully.'));
    }
}
