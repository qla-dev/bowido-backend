<?php

namespace App\Modules\ServiceReports\Controllers;

use App\Modules\ServiceReports\DTOs\ServiceReportData;
use App\Modules\ServiceReports\Models\ServiceReport;
use App\Modules\ServiceReports\Requests\ListServiceReportsRequest;
use App\Modules\ServiceReports\Requests\StoreServiceReportRequest;
use App\Modules\ServiceReports\Requests\UpdateServiceReportRequest;
use App\Modules\ServiceReports\Resources\ServiceReportResource;
use App\Modules\ServiceReports\Services\ServiceReportService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class ServiceReportController extends ApiController
{
    public function __construct(private readonly ServiceReportService $serviceReportService)
    {
    }

    public function index(ListServiceReportsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceReport::class);

        return $this->successCollection(
            $this->serviceReportService->paginate(ListQueryData::fromRequest($request), $request->user()),
            ServiceReportResource::class,
            __('Service reports retrieved successfully.'),
        );
    }

    public function store(StoreServiceReportRequest $request): JsonResponse
    {
        $this->authorize('create', ServiceReport::class);

        $serviceReport = $this->serviceReportService->create(
            ServiceReportData::fromArray([
                ...$request->validated(),
                'image' => $request->file('image'),
                'images' => $request->file('images', []),
            ]),
            $request->user(),
        );

        return $this->successItem($serviceReport, ServiceReportResource::class, __('Service report created successfully.'), 201);
    }

    public function show(ServiceReport $serviceReport): JsonResponse
    {
        $this->authorize('view', $serviceReport);

        return $this->successItem(
            $this->serviceReportService->find($serviceReport->id, request()->user()),
            ServiceReportResource::class,
            __('Service report retrieved successfully.'),
        );
    }

    public function update(UpdateServiceReportRequest $request, ServiceReport $serviceReport): JsonResponse
    {
        $this->authorize('update', $serviceReport);

        $updatedServiceReport = $this->serviceReportService->update(
            $serviceReport,
            ServiceReportData::fromArray([
                ...$serviceReport->toArray(),
                ...$request->validated(),
                'image' => $request->file('image'),
                'images' => $request->file('images', []),
            ]),
            $request->user(),
        );

        return $this->successItem($updatedServiceReport, ServiceReportResource::class, __('Service report updated successfully.'));
    }

    public function destroy(ServiceReport $serviceReport): JsonResponse
    {
        $this->authorize('delete', $serviceReport);

        $this->serviceReportService->delete($serviceReport->id, request()->user());

        return $this->success(null, __('Service report deleted successfully.'));
    }
}
