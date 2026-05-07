<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Pallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class AuditLogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'audit_logs', 'list');
        [$limit, $offset, $filters] = $this->listParams($request, [
            'pallet_id' => ['sometimes', 'integer', 'exists:pallets,id'],
            'made_by_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'event_type' => ['sometimes', 'string', 'max:255'],
            'old_status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
            'new_status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
        ]);

        [$items, $meta] = $this->queryLogs($filters, $request->user(), $limit, $offset);

        return $this->successCollection($items, 'audit_log', 'Audit logs retrieved successfully.', $meta);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'audit_logs', 'create');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        $validated = $request->validate([
            'pallet_id' => ['required', 'integer', 'exists:pallets,id'],
            'event_type' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'old_status_id' => ['nullable', 'integer', 'exists:statuses,id'],
            'new_status_id' => ['nullable', 'integer', 'exists:statuses,id'],
            'old_client_id' => ['nullable', 'integer', 'exists:users,id'],
            'new_client_id' => ['nullable', 'integer', 'exists:users,id'],
            'old_location' => ['nullable', 'string', 'max:255'],
            'new_location' => ['nullable', 'string', 'max:255'],
            'old_qr_code' => ['nullable', 'string', 'max:255'],
            'new_qr_code' => ['nullable', 'string', 'max:255'],
            'context' => ['nullable', 'array'],
        ]);

        $auditLog = AuditLog::query()->create([
            ...$validated,
            'made_by_user_id' => $request->user()->id,
            'note' => $this->normalizeText($validated['note'] ?? null),
            'old_location' => $this->normalizeText($validated['old_location'] ?? null),
            'new_location' => $this->normalizeText($validated['new_location'] ?? null),
            'old_qr_code' => $validated['old_qr_code'] ?? null,
            'new_qr_code' => $validated['new_qr_code'] ?? null,
        ])->load(['pallet.currentStatus', 'madeByUser.role', 'oldStatus', 'newStatus', 'oldClient.role', 'newClient.role']);

        return $this->successItem($auditLog, 'audit_log', 'Audit log created successfully.', 201);
    }

    public function show(Request $request, AuditLog $auditLog): JsonResponse
    {
        $this->authorizeModule($request, 'audit_logs', 'view');
        $ownerId = $auditLog->pallet()->value('user_id');
        if ($ownerId !== null) {
            $this->authorizeCustomerOwner($request, $ownerId, 'You are not allowed to view another customer\'s audit log.');
        }

        return $this->successItem(
            $auditLog->load(['pallet.currentStatus', 'madeByUser.role', 'oldStatus', 'newStatus', 'oldClient.role', 'newClient.role']),
            'audit_log',
            'Audit log retrieved successfully.',
        );
    }

    public function update(Request $request, AuditLog $auditLog): JsonResponse
    {
        $this->authorizeModule($request, 'audit_logs', 'update');
        abort_if(! $request->user()->isAdmin(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        $validated = $request->validate([
            'note' => ['nullable', 'string'],
            'context' => ['nullable', 'array'],
        ]);

        $auditLog->fill([
            'note' => array_key_exists('note', $validated) ? $this->normalizeText($validated['note']) : $auditLog->note,
            'context' => $validated['context'] ?? $auditLog->context,
        ]);
        $auditLog->save();

        return $this->successItem(
            $auditLog->fresh(['pallet.currentStatus', 'madeByUser.role', 'oldStatus', 'newStatus', 'oldClient.role', 'newClient.role']),
            'audit_log',
            'Audit log updated successfully.',
        );
    }

    public function destroy(Request $request, AuditLog $auditLog): JsonResponse
    {
        $this->authorizeModule($request, 'audit_logs', 'delete');
        abort_if(! $request->user()->isAdmin(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');
        $auditLog->delete();

        return $this->success(null, 'Audit log deleted successfully.');
    }

    public function history(Pallet $pallet, Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'audit_logs', 'view');
        $this->authorizeCustomerOwner($request, $pallet->user_id, 'You are not allowed to view another customer\'s pallet history.');

        [$limit, $offset, $filters] = $this->listParams($request, $this->auditFilterRules());
        [$items, $meta] = $this->queryLogs($filters + ['pallet_id' => $pallet->id], $request->user(), $limit, $offset);

        return $this->successCollection($items, 'audit_log', 'Pallet history retrieved successfully.', $meta);
    }

    public function filter(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'audit_logs', 'list');
        [$limit, $offset, $filters] = $this->listParams($request, $this->auditFilterRules());
        [$items, $meta] = $this->queryLogs($filters, $request->user(), $limit, $offset);

        return $this->successCollection($items, 'audit_log', 'Filtered audit logs retrieved successfully.', $meta);
    }

    public function qrVersions(Pallet $pallet, Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'audit_logs', 'view');
        $this->authorizeCustomerOwner($request, $pallet->user_id, 'You are not allowed to view another customer\'s pallet history.');

        $logs = AuditLog::query()
            ->with(['madeByUser.role'])
            ->where('pallet_id', $pallet->id)
            ->whereNotNull('qr_code_version')
            ->orderBy('qr_code_version')
            ->get();

        return $this->success($this->serializeMany($logs, 'audit_log'), 'Pallet QR versions retrieved successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function auditFilterRules(): array
    {
        return [
            'pallet_id' => ['sometimes', 'integer', 'exists:pallets,id'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'old_status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
            'new_status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, AuditLog>, 1: array<string, int>}
     */
    private function queryLogs(array $filters, User $actor, int $limit, int $offset): array
    {
        $query = AuditLog::query()
            ->with(['pallet.currentStatus', 'madeByUser.role', 'oldStatus', 'newStatus', 'oldClient.role', 'newClient.role'])
            ->when(
                $actor->isCustomer(),
                fn (Builder $builder) => $builder->whereHas('pallet', fn (Builder $palletQuery) => $palletQuery->where('user_id', $actor->id)),
            );

        if (($filters['pallet_id'] ?? null) !== null) {
            $query->where('pallet_id', (int) $filters['pallet_id']);
        }

        if (($filters['user_id'] ?? null) !== null) {
            $query->where('made_by_user_id', (int) $filters['user_id']);
        }

        if (($filters['old_status_id'] ?? null) !== null) {
            $query->where('old_status_id', (int) $filters['old_status_id']);
        }

        if (($filters['new_status_id'] ?? null) !== null) {
            $query->where('new_status_id', (int) $filters['new_status_id']);
        }

        if (($filters['date_from'] ?? null) !== null) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (($filters['date_to'] ?? null) !== null) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $query->latest('id');

        return $this->paginateQuery($query, $limit, $offset);
    }
}