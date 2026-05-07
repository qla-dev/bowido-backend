<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CustomerDetail;
use App\Models\GhostPalletReport;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use App\Models\Pallet;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\ServiceReport;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends Controller
{
    protected function success(
        mixed $data,
        string $message,
        array $meta = [],
        array $errors = [],
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'meta' => $meta,
            'errors' => $errors,
        ], $status);
    }

    protected function successItem(
        Model $model,
        string $serializer,
        string $message,
        int $status = 200,
    ): JsonResponse {
        return $this->success(
            data: $this->serializeModel($model, $serializer),
            message: $message,
            status: $status,
        );
    }

    protected function successCollection(
        iterable $items,
        string $serializer,
        string $message,
        array $meta = [],
    ): JsonResponse {
        return $this->success(
            data: $this->serializeMany($items, $serializer),
            message: $message,
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $filterRules
     * @return array{0: int, 1: int, 2: array<string, mixed>}
     */
    protected function listParams(Request $request, array $filterRules = []): array
    {
        $validated = $request->validate(array_merge([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ], $filterRules));

        $limit = (int) ($validated['limit'] ?? 25);
        $offset = (int) ($validated['offset'] ?? 0);

        unset($validated['limit'], $validated['offset']);

        return [$limit, $offset, $validated];
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, mixed>, 1: array<string, int>}
     */
    protected function paginateQuery(Builder $query, int $limit, int $offset): array
    {
        $total = (clone $query)->count();
        $items = $query->offset($offset)->limit($limit)->get();

        return [$items, $this->paginationMeta($total, $limit, $offset, $items->count())];
    }

    /**
     * @param  Collection<int, mixed>  $collection
     * @return array{0: Collection<int, mixed>, 1: array<string, int>}
     */
    protected function paginateCollection(Collection $collection, int $limit, int $offset): array
    {
        $items = $collection->slice($offset, $limit)->values();

        return [$items, $this->paginationMeta($collection->count(), $limit, $offset, $items->count())];
    }

    protected function authorizeModule(Request $request, string $moduleSlug, string $ability): void
    {
        $user = $request->user();

        abort_if(
            ! $user || ! $user->is_active || ! $user->hasModulePermission($moduleSlug, $ability),
            Response::HTTP_FORBIDDEN,
            'This action is unauthorized.',
        );
    }

    protected function authorizeCustomerOwner(Request $request, int $ownerId, string $message): void
    {
        $user = $request->user();

        abort_if(
            $user && $user->isCustomer() && $user->id !== $ownerId,
            Response::HTTP_FORBIDDEN,
            $message,
        );
    }

    protected function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function serializeMany(iterable $items, string $serializer): array
    {
        $data = [];

        foreach ($items as $item) {
            if ($item instanceof Model) {
                $data[] = $this->serializeModel($item, $serializer);
            }
        }

        return $data;
    }

    protected function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'role_id' => $user->role_id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'is_active' => $user->is_active,
            'email_verified_at' => $user->email_verified_at,
            'last_login_at' => $user->last_login_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'role' => $this->serializeRelatedModel($user, 'role', 'role'),
            'customer_detail' => $this->serializeRelatedModel($user, 'customerDetail', 'customer_detail'),
        ];
    }

    protected function serializeRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'is_active' => $role->is_active,
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
            'role_permissions' => $this->serializeRelatedCollection($role, 'rolePermissions', 'role_permission'),
        ];
    }

    protected function serializeCustomerDetail(CustomerDetail $customerDetail): array
    {
        return [
            'id' => $customerDetail->id,
            'user_id' => $customerDetail->user_id,
            'company_name' => $customerDetail->company_name,
            'country' => $customerDetail->country,
            'kvk' => $customerDetail->kvk,
            'billing_email' => $customerDetail->billing_email,
            'billing_address' => $customerDetail->billing_address,
            'delivery_address' => $customerDetail->delivery_address,
            'tax_number' => $customerDetail->tax_number,
            'vat_number' => $customerDetail->vat_number,
            'default_price_per_day' => $customerDetail->default_price_per_day,
            'grace_period_days' => $customerDetail->grace_period_days,
            'notes' => $customerDetail->notes,
            'is_active' => $customerDetail->is_active,
            'created_at' => $customerDetail->created_at,
            'updated_at' => $customerDetail->updated_at,
            'user' => $this->serializeRelatedModel($customerDetail, 'user', 'user'),
        ];
    }

    protected function serializeStatus(Status $status): array
    {
        return [
            'id' => $status->id,
            'name' => $status->name,
            'slug' => $status->slug,
            'description' => $status->description,
            'is_billable' => $status->is_billable,
            'is_active' => $status->is_active,
            'sort_order' => $status->sort_order,
            'created_at' => $status->created_at,
            'updated_at' => $status->updated_at,
        ];
    }

    protected function serializePallet(Pallet $pallet): array
    {
        return [
            'id' => $pallet->id,
            'user_id' => $pallet->user_id,
            'current_status_id' => $pallet->current_status_id,
            'asset_type' => $pallet->asset_type,
            'qr_code' => $pallet->qr_code,
            'reference_code' => $pallet->reference_code,
            'current_location' => $pallet->current_location,
            'notes' => $pallet->notes,
            'last_status_changed_at' => $pallet->last_status_changed_at,
            'is_active' => $pallet->is_active,
            'is_ghost' => $pallet->is_ghost,
            'metadata' => $pallet->metadata,
            'created_at' => $pallet->created_at,
            'updated_at' => $pallet->updated_at,
            'user' => $this->serializeRelatedModel($pallet, 'user', 'user'),
            'current_status' => $this->serializeRelatedModel($pallet, 'currentStatus', 'status'),
        ];
    }

    protected function serializeAuditLog(AuditLog $auditLog): array
    {
        return [
            'id' => $auditLog->id,
            'pallet_id' => $auditLog->pallet_id,
            'made_by_user_id' => $auditLog->made_by_user_id,
            'event_type' => $auditLog->event_type,
            'note' => $auditLog->note,
            'old_status_id' => $auditLog->old_status_id,
            'new_status_id' => $auditLog->new_status_id,
            'old_client_id' => $auditLog->old_client_id,
            'new_client_id' => $auditLog->new_client_id,
            'old_location' => $auditLog->old_location,
            'new_location' => $auditLog->new_location,
            'qr_code_version' => $auditLog->qr_code_version,
            'old_qr_code' => $auditLog->old_qr_code,
            'new_qr_code' => $auditLog->new_qr_code,
            'context' => $auditLog->context,
            'created_at' => $auditLog->created_at,
            'updated_at' => $auditLog->updated_at,
            'pallet' => $this->serializeRelatedModel($auditLog, 'pallet', 'pallet'),
            'made_by_user' => $this->serializeRelatedModel($auditLog, 'madeByUser', 'user'),
            'old_status' => $this->serializeRelatedModel($auditLog, 'oldStatus', 'status'),
            'new_status' => $this->serializeRelatedModel($auditLog, 'newStatus', 'status'),
            'old_client' => $this->serializeRelatedModel($auditLog, 'oldClient', 'user'),
            'new_client' => $this->serializeRelatedModel($auditLog, 'newClient', 'user'),
        ];
    }

    protected function serializeServiceReport(ServiceReport $serviceReport): array
    {
        return [
            'id' => $serviceReport->id,
            'pallet_id' => $serviceReport->pallet_id,
            'reported_by_user_id' => $serviceReport->reported_by_user_id,
            'resolved_by_user_id' => $serviceReport->resolved_by_user_id,
            'status' => $serviceReport->status,
            'severity' => $serviceReport->severity,
            'issue_type' => $serviceReport->issue_type,
            'problem_description' => $serviceReport->problem_description,
            'description' => $serviceReport->description,
            'resolution_note' => $serviceReport->resolution_note,
            'image_path' => $serviceReport->image_path,
            'resolved_at' => $serviceReport->resolved_at,
            'metadata' => $serviceReport->metadata,
            'created_at' => $serviceReport->created_at,
            'updated_at' => $serviceReport->updated_at,
            'pallet' => $this->serializeRelatedModel($serviceReport, 'pallet', 'pallet'),
            'reported_by_user' => $this->serializeRelatedModel($serviceReport, 'reportedByUser', 'user'),
            'resolved_by_user' => $this->serializeRelatedModel($serviceReport, 'resolvedByUser', 'user'),
        ];
    }

    protected function serializeGhostPalletReport(GhostPalletReport $ghostPalletReport): array
    {
        return [
            'id' => $ghostPalletReport->id,
            'user_id' => $ghostPalletReport->user_id,
            'paired_pallet_id' => $ghostPalletReport->paired_pallet_id,
            'status' => $ghostPalletReport->status,
            'quantity' => $ghostPalletReport->quantity,
            'location' => $ghostPalletReport->location,
            'description' => $ghostPalletReport->description,
            'notes' => $ghostPalletReport->notes,
            'reported_at' => $ghostPalletReport->reported_at,
            'paired_at' => $ghostPalletReport->paired_at,
            'metadata' => $ghostPalletReport->metadata,
            'created_at' => $ghostPalletReport->created_at,
            'updated_at' => $ghostPalletReport->updated_at,
            'user' => $this->serializeRelatedModel($ghostPalletReport, 'user', 'user'),
            'paired_pallet' => $this->serializeRelatedModel($ghostPalletReport, 'pairedPallet', 'pallet'),
        ];
    }

    protected function serializeInvoiceItem(InvoiceItem $invoiceItem): array
    {
        return [
            'id' => $invoiceItem->id,
            'invoice_id' => $invoiceItem->invoice_id,
            'pallet_id' => $invoiceItem->pallet_id,
            'description' => $invoiceItem->description,
            'period_start' => $invoiceItem->period_start?->toDateString(),
            'period_end' => $invoiceItem->period_end?->toDateString(),
            'billed_days' => $invoiceItem->billed_days,
            'price_per_day' => $invoiceItem->price_per_day,
            'amount' => $invoiceItem->amount,
            'metadata' => $invoiceItem->metadata,
            'created_at' => $invoiceItem->created_at,
            'updated_at' => $invoiceItem->updated_at,
            'pallet' => $this->serializeRelatedModel($invoiceItem, 'pallet', 'pallet'),
        ];
    }

    protected function serializeInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'user_id' => $invoice->user_id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'currency' => $invoice->currency,
            'billing_period_start' => $invoice->billing_period_start?->toDateString(),
            'billing_period_end' => $invoice->billing_period_end?->toDateString(),
            'period_start' => $invoice->period_start?->toDateString(),
            'period_end' => $invoice->period_end?->toDateString(),
            'issued_at' => $invoice->issued_at,
            'due_at' => $invoice->due_at?->toDateString(),
            'paid_at' => $invoice->paid_at,
            'subtotal_amount' => $invoice->subtotal_amount,
            'total_amount' => $invoice->total_amount,
            'notes' => $invoice->notes,
            'created_at' => $invoice->created_at,
            'updated_at' => $invoice->updated_at,
            'user' => $this->serializeRelatedModel($invoice, 'user', 'user'),
            'items' => $this->serializeRelatedCollection($invoice, 'items', 'invoice_item'),
        ];
    }

    protected function serializeModule(Module $module): array
    {
        return [
            'id' => $module->id,
            'name' => $module->name,
            'slug' => $module->slug,
            'description' => $module->description,
            'is_active' => $module->is_active,
            'created_at' => $module->created_at,
            'updated_at' => $module->updated_at,
            'role_permissions' => $this->serializeRelatedCollection($module, 'rolePermissions', 'role_permission'),
        ];
    }

    protected function serializeRolePermission(RolePermission $rolePermission): array
    {
        return [
            'id' => $rolePermission->id,
            'role_id' => $rolePermission->role_id,
            'module_id' => $rolePermission->module_id,
            'can_list' => $rolePermission->can_list,
            'can_view' => $rolePermission->can_view,
            'can_create' => $rolePermission->can_create,
            'can_update' => $rolePermission->can_update,
            'can_delete' => $rolePermission->can_delete,
            'created_at' => $rolePermission->created_at,
            'updated_at' => $rolePermission->updated_at,
            'role' => $this->serializeRelatedModel($rolePermission, 'role', 'role'),
            'module' => $this->serializeRelatedModel($rolePermission, 'module', 'module'),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function paginationMeta(int $total, int $limit, int $offset, int $count): array
    {
        return [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'count' => $count,
        ];
    }

    private function serializeModel(Model $model, string $serializer): array
    {
        return match ($serializer) {
            'audit_log' => $this->serializeAuditLog($model),
            'customer_detail' => $this->serializeCustomerDetail($model),
            'ghost_pallet_report' => $this->serializeGhostPalletReport($model),
            'invoice' => $this->serializeInvoice($model),
            'invoice_item' => $this->serializeInvoiceItem($model),
            'module' => $this->serializeModule($model),
            'pallet' => $this->serializePallet($model),
            'role' => $this->serializeRole($model),
            'role_permission' => $this->serializeRolePermission($model),
            'service_report' => $this->serializeServiceReport($model),
            'status' => $this->serializeStatus($model),
            'user' => $this->serializeUser($model),
            default => $model->toArray(),
        };
    }

    private function serializeRelatedModel(Model $model, string $relation, string $serializer): ?array
    {
        if (! $model->relationLoaded($relation)) {
            return null;
        }

        $related = $model->getRelation($relation);

        if (! $related instanceof Model) {
            return null;
        }

        return $this->serializeModel($related, $serializer);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeRelatedCollection(Model $model, string $relation, string $serializer): array
    {
        if (! $model->relationLoaded($relation)) {
            return [];
        }

        $related = $model->getRelation($relation);

        if (! is_iterable($related)) {
            return [];
        }

        return $this->serializeMany($related, $serializer);
    }
}