<?php

namespace App\Http\Controllers;

use App\Models\GhostPalletReport;
use App\Services\GhostPalletReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class GhostPalletReportController extends ApiController
{
    public function __construct(private readonly GhostPalletReportService $ghostPalletReportService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'ghost_pallet_reports', 'list');
        [$limit, $offset, $filters] = $this->listParams($request, [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'paired_pallet_id' => ['sometimes', 'integer', 'exists:pallets,id'],
            'status' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'max:255'],
        ]);

        $query = GhostPalletReport::query()
            ->with(['user.role', 'pairedPallet.currentStatus'])
            ->when($request->user()->isCustomer(), fn ($builder) => $builder->where('user_id', $request->user()->id))
            ->when($filters['user_id'] ?? null, fn ($builder, $value) => $builder->where('user_id', (int) $value))
            ->when($filters['paired_pallet_id'] ?? null, fn ($builder, $value) => $builder->where('paired_pallet_id', (int) $value))
            ->when($filters['status'] ?? null, fn ($builder, $value) => $builder->where('status', $value))
            ->when($filters['location'] ?? null, fn ($builder, $value) => $builder->where('location', 'like', '%'.$value.'%'))
            ->latest('id');

        [$items, $meta] = $this->paginateQuery($query, $limit, $offset);

        return $this->successCollection($items, 'ghost_pallet_report', 'Ghost pallet reports retrieved successfully.', $meta);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'ghost_pallet_reports', 'create');

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $ghostPalletReport = $this->ghostPalletReportService->create($validated, $request->user());

        return $this->successItem($ghostPalletReport, 'ghost_pallet_report', 'Ghost pallet report created successfully.', 201);
    }

    public function show(Request $request, GhostPalletReport $ghostPalletReport): JsonResponse
    {
        $this->authorizeModule($request, 'ghost_pallet_reports', 'view');
        $this->authorizeCustomerOwner($request, $ghostPalletReport->user_id, 'You are not allowed to view another customer\'s ghost pallet report.');

        return $this->successItem($ghostPalletReport->load(['user.role', 'pairedPallet.currentStatus']), 'ghost_pallet_report', 'Ghost pallet report retrieved successfully.');
    }

    public function update(Request $request, GhostPalletReport $ghostPalletReport): JsonResponse
    {
        $this->authorizeModule($request, 'ghost_pallet_reports', 'update');
        $this->authorizeCustomerOwner($request, $ghostPalletReport->user_id, 'You are not allowed to update another customer\'s ghost pallet report.');

        $validated = $request->validate([
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'paired_pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            'status' => ['sometimes', 'in:open,paired'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $updatedGhostPalletReport = $this->ghostPalletReportService->update($ghostPalletReport, $validated, $request->user());

        return $this->successItem($updatedGhostPalletReport, 'ghost_pallet_report', 'Ghost pallet report updated successfully.');
    }

    public function destroy(Request $request, GhostPalletReport $ghostPalletReport): JsonResponse
    {
        $this->authorizeModule($request, 'ghost_pallet_reports', 'delete');
        $this->authorizeCustomerOwner($request, $ghostPalletReport->user_id, 'You are not allowed to delete another customer\'s ghost pallet report.');
        $ghostPalletReport->delete();

        return $this->success(null, 'Ghost pallet report deleted successfully.');
    }

    public function report(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'ghost_pallet_reports', 'create');

        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $ghostReport = $this->ghostPalletReportService->reportPallets($validated, $request->user());

        return $this->successItem($ghostReport, 'ghost_pallet_report', 'Ghost pallet report created successfully.', 201);
    }

    public function pair(Request $request, GhostPalletReport $ghostPalletReport): JsonResponse
    {
        $this->authorizeModule($request, 'ghost_pallet_reports', 'update');
        $this->authorizeCustomerOwner($request, $ghostPalletReport->user_id, 'You are not allowed to update another customer\'s ghost pallet report.');

        $validated = $request->validate([
            'pallet_id' => ['required', 'integer', 'exists:pallets,id'],
            'qr_code' => ['nullable', 'string', 'max:255'],
            'quantity_to_pair' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
        ]);

        $pairedReport = $this->ghostPalletReportService->pairReport($ghostPalletReport, $validated, $request->user());

        return $this->successItem($pairedReport, 'ghost_pallet_report', 'Ghost pallet report paired successfully.');
    }

    public function active(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'ghost_pallet_reports', 'list');
        [$limit, $offset, $filters] = $this->listParams($request, [
            'customer_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);
        $query = GhostPalletReport::query()
            ->with(['user.role', 'pairedPallet.currentStatus'])
            ->when($request->user()->isCustomer(), fn (Builder $builder) => $builder->where('user_id', $request->user()->id));

        if (($customerId = $filters['customer_id'] ?? null) !== null) {
            $query->where('user_id', (int) $customerId);
        }

        $collection = $query->latest('id')->get()
            ->filter(function (GhostPalletReport $report): bool {
                $pairedQuantity = (int) data_get($report->metadata, 'paired_quantity', 0);

                return $report->status === GhostPalletReport::STATUS_OPEN || $pairedQuantity < $report->quantity;
            })
            ->values();

        [$items, $meta] = $this->paginateCollection($collection, $limit, $offset);

        return $this->successCollection($items, 'ghost_pallet_report', 'Active ghost pallet reports retrieved successfully.', $meta);
    }
}