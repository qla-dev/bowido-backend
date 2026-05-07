<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GhostPalletReport;
use App\Models\Pallet;
use App\Models\Status;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\BillingCounterService;
use App\Services\PalletStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PalletController extends ApiController
{
    public function __construct(
        private readonly BillingCounterService $billingCounterService,
        private readonly PalletStatusService $palletStatusService,
        private readonly AuditTrailService $auditTrailService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'pallets', 'list');
        [$limit, $offset, $filters] = $this->listParams($request, [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'current_status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
            'asset_type' => ['sometimes', 'string', 'max:255'],
            'qr_code' => ['sometimes', 'string', 'max:255'],
            'reference_code' => ['sometimes', 'string', 'max:255'],
            'current_location' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_ghost' => ['sometimes', 'boolean'],
        ]);

        $query = $this->scopedQuery($request->user())
            ->when($filters['user_id'] ?? null, fn ($builder, $value) => $builder->where('user_id', (int) $value))
            ->when($filters['current_status_id'] ?? null, fn ($builder, $value) => $builder->where('current_status_id', (int) $value))
            ->when($filters['asset_type'] ?? null, fn ($builder, $value) => $builder->where('asset_type', 'like', '%'.$value.'%'))
            ->when($filters['qr_code'] ?? null, fn ($builder, $value) => $builder->where('qr_code', Pallet::normalizeQrCode((string) $value)))
            ->when($filters['reference_code'] ?? null, fn ($builder, $value) => $builder->where('reference_code', 'like', '%'.$value.'%'))
            ->when($filters['current_location'] ?? null, fn ($builder, $value) => $builder->where('current_location', 'like', '%'.$value.'%'))
            ->when(array_key_exists('is_active', $filters), fn ($builder) => $builder->where('is_active', (bool) $filters['is_active']))
            ->when(array_key_exists('is_ghost', $filters), fn ($builder) => $builder->where('is_ghost', (bool) $filters['is_ghost']))
            ->latest('id');

        [$items, $meta] = $this->paginateQuery($query, $limit, $offset);

        return $this->successCollection($items, 'pallet', 'Pallets retrieved successfully.', $meta);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'pallets', 'create');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        $validated = $request->validate($this->storeRules());
        $this->ensureActiveUser((int) $validated['user_id']);
        $this->ensureActiveStatus((int) $validated['current_status_id']);

        $pallet = DB::transaction(function () use ($validated, $request): Pallet {
            /** @var Pallet $pallet */
            $pallet = Pallet::query()->create([
                'user_id' => (int) $validated['user_id'],
                'current_status_id' => (int) $validated['current_status_id'],
                'type' => $validated['type'] ?? $validated['asset_type'] ?? 'pallet',
                'asset_type' => $validated['asset_type'] ?? 'pallet',
                'qr_code' => Pallet::normalizeQrCode((string) $validated['qr_code']),
                'reference_code' => $this->normalizeText($validated['reference_code'] ?? null),
                'current_location' => $this->normalizeText($validated['current_location'] ?? null),
                'notes' => $this->normalizeText($validated['notes'] ?? null),
                'last_status_changed_at' => now(),
                'is_active' => $validated['is_active'] ?? true,
                'is_ghost' => $validated['is_ghost'] ?? false,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            $this->auditTrailService->record(
                palletId: $pallet->id,
                madeByUserId: $request->user()->id,
                eventType: AuditLog::EVENT_CREATED,
                newStatusId: $pallet->current_status_id,
                newClientId: $pallet->user_id,
                newLocation: $pallet->current_location,
                newQrCode: $pallet->qr_code,
                qrCodeVersion: 1,
                note: $pallet->notes,
                context: ['asset_type' => $pallet->asset_type],
            );

            return $pallet->load(['user.role', 'user.customerDetail', 'currentStatus']);
        });

        return $this->successItem($pallet, 'pallet', 'Pallet created successfully.', 201);
    }

    public function show(Request $request, Pallet $pallet): JsonResponse
    {
        $this->authorizeModule($request, 'pallets', 'view');
        $this->authorizeCustomerOwner($request, $pallet->user_id, 'You are not allowed to view another customer\'s pallet.');

        return $this->successItem($pallet->load(['user.role', 'user.customerDetail', 'currentStatus']), 'pallet', 'Pallet retrieved successfully.');
    }

    public function update(Request $request, Pallet $pallet): JsonResponse
    {
        $this->authorizeModule($request, 'pallets', 'update');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        $validated = $request->validate($this->updateRules($pallet));

        if (($validated['user_id'] ?? null) !== null) {
            $this->ensureActiveUser((int) $validated['user_id']);
        }

        if (($validated['current_status_id'] ?? null) !== null) {
            $this->ensureActiveStatus((int) $validated['current_status_id']);
        }

        $updatedPallet = DB::transaction(function () use ($pallet, $validated, $request): Pallet {
            $lockedPallet = Pallet::query()->lockForUpdate()->findOrFail($pallet->id);
            $original = $lockedPallet->only(['user_id', 'current_status_id', 'current_location', 'qr_code']);
            $originalAttributes = $lockedPallet->getAttributes();

            $payload = [
                'user_id' => $validated['user_id'] ?? $lockedPallet->user_id,
                'current_status_id' => $validated['current_status_id'] ?? $lockedPallet->current_status_id,
                'type' => $validated['type'] ?? $lockedPallet->type,
                'asset_type' => $validated['asset_type'] ?? $lockedPallet->asset_type,
                'reference_code' => array_key_exists('reference_code', $validated) ? $this->normalizeText($validated['reference_code']) : $lockedPallet->reference_code,
                'current_location' => array_key_exists('current_location', $validated) ? $this->normalizeText($validated['current_location']) : $lockedPallet->current_location,
                'notes' => array_key_exists('notes', $validated) ? $this->normalizeText($validated['notes']) : $lockedPallet->notes,
                'is_active' => $validated['is_active'] ?? $lockedPallet->is_active,
                'is_ghost' => $validated['is_ghost'] ?? $lockedPallet->is_ghost,
                'metadata' => array_key_exists('metadata', $validated) ? $validated['metadata'] : $lockedPallet->metadata,
            ];

            if (isset($validated['qr_code'])) {
                $payload['qr_code'] = Pallet::normalizeQrCode((string) $validated['qr_code']);
            }

            if ((int) $original['current_status_id'] !== (int) $payload['current_status_id']) {
                $payload['last_status_changed_at'] = now();
            }

            $lockedPallet->fill($payload);
            $lockedPallet->save();

            $this->recordMutationAudits($lockedPallet, $original, $payload, $originalAttributes, $request->user());

            return $lockedPallet->fresh(['user.role', 'user.customerDetail', 'currentStatus']);
        });

        return $this->successItem($updatedPallet, 'pallet', 'Pallet updated successfully.');
    }

    public function destroy(Request $request, Pallet $pallet): JsonResponse
    {
        $this->authorizeModule($request, 'pallets', 'delete');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        if (
            $pallet->auditLogs()->exists()
            || $pallet->serviceReports()->exists()
            || $pallet->invoiceItems()->exists()
            || GhostPalletReport::query()->where('paired_pallet_id', $pallet->id)->exists()
        ) {
            throw ValidationException::withMessages([
                'pallet' => ['Pallets with linked history, reports, ghost pairings, or invoice items cannot be deleted.'],
            ]);
        }

        $pallet->delete();

        return $this->success(null, 'Pallet deleted successfully.');
    }

    public function scan(string $qrCode, Request $request): JsonResponse
    {
        $pallet = Pallet::query()
            ->with(['user.role', 'user.customerDetail', 'currentStatus', 'auditLogs.newStatus'])
            ->where('qr_code', Pallet::normalizeQrCode($qrCode))
            ->firstOrFail();

        $this->authorizeModule($request, 'pallets', 'view');
        $this->authorizeCustomerOwner($request, $pallet->user_id, 'You are not allowed to view another customer\'s pallet.');

        return $this->success([
            'pallet' => $this->serializePallet($pallet),
            'counters' => $this->billingCounterService->calculateForPallet($pallet),
            'allowed_actions' => $this->palletStatusService->allowedNextActions($pallet, $request->user()),
        ], 'Pallet scan data retrieved successfully.');
    }

    public function changeStatus(Request $request, Pallet $pallet): JsonResponse
    {
        $this->authorizeModule($request, 'pallets', 'update');
        $this->authorizeCustomerOwner($request, $pallet->user_id, 'You are not allowed to update another customer\'s pallet.');

        $validated = $request->validate([
            'status_id' => ['required', 'integer', 'exists:statuses,id'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'qr_code' => ['nullable', 'string', 'max:255'],
        ]);

        $updatedPallet = $this->palletStatusService->changeStatus($pallet, $validated, $request->user());

        return $this->success([
            'pallet' => $this->serializePallet($updatedPallet),
            'counters' => $this->billingCounterService->calculateForPallet($updatedPallet),
        ], 'Pallet status updated successfully.');
    }

    public function bulkChangeStatus(Request $request): JsonResponse
    {
        abort_if(
            $request->user()->isCustomer() || ! $request->user()->hasModulePermission('pallets', 'update'),
            Response::HTTP_FORBIDDEN,
            'This action is unauthorized.',
        );

        $validated = $request->validate([
            'qr_codes' => ['required', 'array', 'min:1'],
            'qr_codes.*' => ['required', 'string', 'distinct', 'max:255'],
            'status_id' => ['required', 'integer', 'exists:statuses,id'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);
        $updatedPallets = $this->palletStatusService->bulkChangeStatus($validated['qr_codes'], $validated, $request->user());

        return $this->success([
            'count' => $updatedPallets->count(),
            'pallets' => $this->serializeMany($updatedPallets, 'pallet'),
        ], 'Bulk pallet status update completed successfully.');
    }

    public function markReadyForReturn(Request $request, Pallet $pallet): JsonResponse
    {
        $this->authorizeModule($request, 'pallets', 'view');
        $this->authorizeCustomerOwner($request, $pallet->user_id, 'You are not allowed to manage this pallet.');

        $validated = $request->validate([
            'note' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
        ]);
        $updatedPallet = $this->palletStatusService->markReadyForReturn(
            pallet: $pallet,
            note: $validated['note'] ?? ($validated['reason'] ?? null),
            actor: $request->user(),
        );

        return $this->success([
            'pallet' => $this->serializePallet($updatedPallet),
            'counters' => $this->billingCounterService->calculateForPallet($updatedPallet),
        ], 'Pallet marked as ready for return successfully.');
    }

    public function markUnknown(Request $request, Pallet $pallet): JsonResponse
    {
        $this->authorizeModule($request, 'pallets', 'update');
        $validated = $request->validate([
            'note' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
        ]);
        $updatedPallet = $this->palletStatusService->markUnknown(
            pallet: $pallet,
            reason: $validated['reason'] ?? ($validated['note'] ?? null),
            actor: $request->user(),
        );

        return $this->success([
            'pallet' => $this->serializePallet($updatedPallet),
            'counters' => $this->billingCounterService->calculateForPallet($updatedPallet),
        ], 'Pallet marked as unknown successfully.');
    }

    public function returnable(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'pallets', 'list');
        [$limit, $offset, $filters] = $this->listParams($request, $this->filterRules());
        [$items, $meta] = $this->filteredPallets(
            filters: $filters + ['status_id' => $this->statusIdBySlug('pending_return')],
            actor: $request->user(),
            limit: $limit,
            offset: $offset,
        );

        return $this->successCollection($items, 'pallet', 'Returnable pallets retrieved successfully.', $meta);
    }

    public function filter(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'pallets', 'list');
        [$limit, $offset, $filters] = $this->listParams($request, $this->filterRules());
        [$items, $meta] = $this->filteredPallets($filters, $request->user(), $limit, $offset);

        return $this->successCollection($items, 'pallet', 'Filtered pallets retrieved successfully.', $meta);
    }

    public function overdue(Request $request): JsonResponse
    {
        abort_if(! $request->user()->isAdmin(), Response::HTTP_FORBIDDEN, 'Only administrators can view overdue pallets.');
        $this->authorizeModule($request, 'pallets', 'list');
        [$limit, $offset, $filters] = $this->listParams($request, $this->filterRules());
        [$items, $meta] = $this->filteredPallets($filters, $request->user(), $limit, $offset, true);

        return $this->successCollection($items, 'pallet', 'Overdue pallets retrieved successfully.', $meta);
    }

    public function byCustomer(User $customer, Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'pallets', 'list');
        $this->authorizeCustomerOwner($request, $customer->id, 'You are not allowed to view another customer\'s pallets.');
        [$limit, $offset, $filters] = $this->listParams($request, $this->filterRules());
        [$items, $meta] = $this->filteredPallets(
            filters: $filters + ['customer_id' => $customer->id],
            actor: $request->user(),
            limit: $limit,
            offset: $offset,
        );

        return $this->successCollection($items, 'pallet', 'Customer pallets retrieved successfully.', $meta);
    }

    public function serviceList(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'pallets', 'list');
        [$limit, $offset] = $this->listParams($request, $this->filterRules());
        $serviceStatusId = $this->statusIdBySlug('service');
        $query = $this->scopedQuery($request->user())
            ->with(['serviceReports' => fn (Builder $builder) => $builder->latest('id')])
            ->where('current_status_id', $serviceStatusId);

        [$items, $meta] = $this->paginateQuery($query->latest('id'), $limit, $offset);

        $data = $items->map(function (Pallet $pallet): array {
            return [
                'pallet' => $this->serializePallet($pallet),
                'latest_service_report' => $pallet->serviceReports->isNotEmpty()
                    ? $this->serializeServiceReport($pallet->serviceReports->first())
                    : null,
            ];
        })->values();

        return $this->success($data->all(), 'Service pallets retrieved successfully.', $meta);
    }

    public function counter(Request $request, Pallet $pallet): JsonResponse
    {
        $this->authorizeModule($request, 'pallets', 'view');
        $this->authorizeCustomerOwner($request, $pallet->user_id, 'You are not allowed to view another customer\'s pallet.');

        return $this->success($this->billingCounterService->calculateForPallet($pallet), 'Pallet counters calculated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function storeRules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'current_status_id' => ['required', 'integer', 'exists:statuses,id'],
            'type' => ['sometimes', 'string', 'max:255'],
            'asset_type' => ['sometimes', 'string', 'max:255'],
            'qr_code' => ['required', 'string', 'max:255', 'unique:pallets,qr_code'],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'current_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'is_ghost' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(Pallet $pallet): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'current_status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
            'type' => ['sometimes', 'string', 'max:255'],
            'asset_type' => ['sometimes', 'string', 'max:255'],
            'qr_code' => ['sometimes', 'string', 'max:255', Rule::unique('pallets', 'qr_code')->ignore($pallet->id)],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'current_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'is_ghost' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterRules(): array
    {
        return [
            'status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
            'customer_id' => ['sometimes', 'integer', 'exists:users,id'],
            'location' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'max:255'],
            'is_ghost' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'overdue' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, Pallet>, 1: array<string, int>}
     */
    private function filteredPallets(
        array $filters,
        User $actor,
        int $limit,
        int $offset,
        bool $forceOverdue = false,
    ): array {
        $query = $this->scopedQuery($actor);

        if (isset($filters['status_id'])) {
            $query->where('current_status_id', (int) $filters['status_id']);
        }

        if (isset($filters['customer_id'])) {
            $query->where('user_id', (int) $filters['customer_id']);
        }

        if (isset($filters['location'])) {
            $query->where('current_location', 'like', '%'.$filters['location'].'%');
        }

        if (isset($filters['type'])) {
            $query->where('asset_type', 'like', '%'.$filters['type'].'%');
        }

        if (array_key_exists('is_ghost', $filters)) {
            $query->where('is_ghost', (bool) $filters['is_ghost']);
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $collection = $query->latest('id')->get();
        $shouldFilterOverdue = $forceOverdue || array_key_exists('overdue', $filters);

        if ($shouldFilterOverdue) {
            $expectedOverdue = $forceOverdue ? true : (bool) $filters['overdue'];

            $collection = $collection->filter(function (Pallet $pallet) use ($expectedOverdue): bool {
                $isOverdue = ((int) $this->billingCounterService->calculateForPallet($pallet)['overdue_days']) > 0;

                return $expectedOverdue ? $isOverdue : ! $isOverdue;
            })->values();
        }

        return $this->paginateCollection($collection, $limit, $offset);
    }

    private function scopedQuery(User $actor): Builder
    {
        return Pallet::query()
            ->with(['user.role', 'user.customerDetail', 'currentStatus'])
            ->when($actor->isCustomer(), fn (Builder $builder) => $builder->where('user_id', $actor->id));
    }

    private function statusIdBySlug(string $slug): int
    {
        return (int) Status::query()->where('slug', $slug)->value('id');
    }

    private function ensureActiveUser(int $userId): void
    {
        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user is not active.'],
            ]);
        }
    }

    private function ensureActiveStatus(int $statusId): void
    {
        /** @var Status $status */
        $status = Status::query()->findOrFail($statusId);

        if (! $status->is_active) {
            throw ValidationException::withMessages([
                'current_status_id' => ['The selected status is not active.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $originalAttributes
     */
    private function recordMutationAudits(Pallet $pallet, array $original, array $payload, array $originalAttributes, User $actor): void
    {
        $tracked = false;
        $note = $payload['notes'] ?? null;

        if ((int) $original['current_status_id'] !== (int) $pallet->current_status_id) {
            $tracked = true;
            $this->auditTrailService->record(
                palletId: $pallet->id,
                madeByUserId: $actor->id,
                eventType: AuditLog::EVENT_STATUS_CHANGED,
                oldStatusId: (int) $original['current_status_id'],
                newStatusId: (int) $pallet->current_status_id,
                note: $note,
            );
        }

        if ((int) $original['user_id'] !== (int) $pallet->user_id) {
            $tracked = true;
            $this->auditTrailService->record(
                palletId: $pallet->id,
                madeByUserId: $actor->id,
                eventType: AuditLog::EVENT_CLIENT_CHANGED,
                oldClientId: (int) $original['user_id'],
                newClientId: (int) $pallet->user_id,
                note: $note,
            );
        }

        if ((string) ($original['current_location'] ?? '') !== (string) ($pallet->current_location ?? '')) {
            $tracked = true;
            $this->auditTrailService->record(
                palletId: $pallet->id,
                madeByUserId: $actor->id,
                eventType: AuditLog::EVENT_LOCATION_CHANGED,
                oldLocation: $original['current_location'],
                newLocation: $pallet->current_location,
                note: $note,
            );
        }

        if ((string) $original['qr_code'] !== (string) $pallet->qr_code) {
            $tracked = true;
            $this->auditTrailService->record(
                palletId: $pallet->id,
                madeByUserId: $actor->id,
                eventType: AuditLog::EVENT_QR_CODE_CHANGED,
                oldQrCode: $original['qr_code'],
                newQrCode: $pallet->qr_code,
                note: $note,
            );
        }

        if (! $tracked && $originalAttributes !== $pallet->getAttributes()) {
            $this->auditTrailService->record(
                palletId: $pallet->id,
                madeByUserId: $actor->id,
                eventType: AuditLog::EVENT_UPDATED,
                note: $note,
                context: ['fields' => array_keys($payload)],
            );
        }
    }
}