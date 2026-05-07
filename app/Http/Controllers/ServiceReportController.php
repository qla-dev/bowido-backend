<?php

namespace App\Http\Controllers;

use App\Models\Pallet;
use App\Models\ServiceReport;
use App\Services\ServiceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServiceReportController extends ApiController
{
    public function __construct(private readonly ServiceReportService $serviceReportService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'service_reports', 'list');
        [$limit, $offset, $filters] = $this->listParams($request, [
            'pallet_id' => ['sometimes', 'integer', 'exists:pallets,id'],
            'status' => ['sometimes', 'string', 'max:255'],
            'severity' => ['sometimes', 'string', 'max:255'],
            'issue_type' => ['sometimes', 'string', 'max:255'],
            'reported_by_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'resolved_by_user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        $query = ServiceReport::query()
            ->with(['pallet.user.role', 'pallet.currentStatus', 'reportedByUser.role', 'resolvedByUser.role'])
            ->when(
                $request->user()->isCustomer(),
                fn ($builder) => $builder->whereHas('pallet', fn ($palletQuery) => $palletQuery->where('user_id', $request->user()->id)),
            )
            ->when($filters['pallet_id'] ?? null, fn ($builder, $value) => $builder->where('pallet_id', (int) $value))
            ->when($filters['status'] ?? null, fn ($builder, $value) => $builder->where('status', $value))
            ->when($filters['severity'] ?? null, fn ($builder, $value) => $builder->where('severity', $value))
            ->when($filters['issue_type'] ?? null, fn ($builder, $value) => $builder->where('issue_type', $value))
            ->when($filters['reported_by_user_id'] ?? null, fn ($builder, $value) => $builder->where('reported_by_user_id', (int) $value))
            ->when($filters['resolved_by_user_id'] ?? null, fn ($builder, $value) => $builder->where('resolved_by_user_id', (int) $value))
            ->latest('id');

        [$items, $meta] = $this->paginateQuery($query, $limit, $offset);

        return $this->successCollection($items, 'service_report', 'Service reports retrieved successfully.', $meta);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'service_reports', 'create');

        $validated = $request->validate([
            'pallet_id' => ['required', 'integer', 'exists:pallets,id'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'issue_type' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'metadata' => ['nullable', 'array'],
        ]);

        $serviceReport = $this->serviceReportService->create([
            ...$validated,
            'image' => $request->file('image'),
        ], $request->user());

        return $this->successItem($serviceReport, 'service_report', 'Service report created successfully.', 201);
    }

    public function show(Request $request, ServiceReport $serviceReport): JsonResponse
    {
        $this->authorizeModule($request, 'service_reports', 'view');
        $ownerId = $serviceReport->pallet()->value('user_id');
        if ($ownerId !== null) {
            $this->authorizeCustomerOwner($request, $ownerId, 'You are not allowed to view another customer\'s service report.');
        }

        return $this->successItem(
            $serviceReport->load(['pallet.user.role', 'pallet.currentStatus', 'reportedByUser.role', 'resolvedByUser.role']),
            'service_report',
            'Service report retrieved successfully.',
        );
    }

    public function update(Request $request, ServiceReport $serviceReport): JsonResponse
    {
        $this->authorizeModule($request, 'service_reports', 'update');

        $validated = $request->validate([
            'pallet_id' => ['sometimes', 'integer', 'exists:pallets,id'],
            'status' => ['sometimes', 'in:open,resolved'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'issue_type' => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'problem_description' => ['sometimes', 'string'],
            'resolution_note' => ['required_if:status,resolved', 'nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'metadata' => ['nullable', 'array'],
        ]);

        $updatedServiceReport = $this->serviceReportService->update(
            $serviceReport,
            [...$validated, 'image' => $request->file('image')],
            $request->user(),
        );

        return $this->successItem($updatedServiceReport, 'service_report', 'Service report updated successfully.');
    }

    public function destroy(Request $request, ServiceReport $serviceReport): JsonResponse
    {
        $this->authorizeModule($request, 'service_reports', 'delete');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');
        $serviceReport->delete();

        return $this->success(null, 'Service report deleted successfully.');
    }

    public function report(Request $request, Pallet $pallet): JsonResponse
    {
        $this->authorizeModule($request, 'service_reports', 'create');
        $this->authorizeModule($request, 'pallets', 'view');
        $this->authorizeCustomerOwner($request, $pallet->user_id, 'You are not allowed to report damage for this pallet.');

        $validated = $request->validate([
            'problem_description' => ['required', 'string'],
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'location' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'issue_type' => ['nullable', 'string', 'max:255'],
        ]);

        $serviceReport = $this->serviceReportService->reportPalletDamage(
            pallet: $pallet,
            attributes: [...$validated, 'images' => $request->file('images', [])],
            actor: $request->user(),
        );

        return $this->successItem($serviceReport, 'service_report', 'Service report created successfully.', 201);
    }

    public function resolve(Request $request, Pallet $pallet): JsonResponse
    {
        abort_if(
            $request->user()->isCustomer() || ! $request->user()->hasModulePermission('service_reports', 'update'),
            Response::HTTP_FORBIDDEN,
            'This action is unauthorized.',
        );

        $this->authorizeModule($request, 'pallets', 'view');
        $validated = $request->validate([
            'new_status_id' => ['required', 'integer', 'exists:statuses,id'],
            'resolution_note' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $serviceReport = $this->serviceReportService->resolvePalletReport(
            pallet: $pallet,
            newStatusId: (int) $validated['new_status_id'],
            resolutionNote: $validated['resolution_note'],
            location: $validated['location'] ?? null,
            actor: $request->user(),
        );

        return $this->successItem($serviceReport, 'service_report', 'Service report resolved successfully.');
    }
}