<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Pallet;
use App\Services\InvoiceGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class InvoiceItemController extends ApiController
{
    public function __construct(private readonly InvoiceGenerationService $invoiceGenerationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'invoice_items', 'list');
        [$limit, $offset, $filters] = $this->listParams($request, [
            'invoice_id' => ['sometimes', 'integer', 'exists:invoices,id'],
            'pallet_id' => ['sometimes', 'integer', 'exists:pallets,id'],
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date'],
        ]);

        $query = InvoiceItem::query()
            ->with(['invoice.user.role', 'pallet.currentStatus'])
            ->when(
                $request->user()->isCustomer(),
                fn ($builder) => $builder->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('user_id', $request->user()->id)),
            )
            ->when($filters['invoice_id'] ?? null, fn ($builder, $value) => $builder->where('invoice_id', (int) $value))
            ->when($filters['pallet_id'] ?? null, fn ($builder, $value) => $builder->where('pallet_id', (int) $value))
            ->when($filters['period_start'] ?? null, fn ($builder, $value) => $builder->whereDate('period_start', '>=', $value))
            ->when($filters['period_end'] ?? null, fn ($builder, $value) => $builder->whereDate('period_end', '<=', $value))
            ->latest('id');

        [$items, $meta] = $this->paginateQuery($query, $limit, $offset);

        return $this->successCollection($items, 'invoice_item', 'Invoice items retrieved successfully.', $meta);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'invoice_items', 'create');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            'description' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'billed_days' => ['required', 'integer', 'min:0'],
            'price_per_day' => ['required', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        $invoiceItem = DB::transaction(function () use ($validated): InvoiceItem {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->findOrFail((int) $validated['invoice_id']);

            if (($validated['pallet_id'] ?? null) !== null) {
                $pallet = Pallet::query()->findOrFail((int) $validated['pallet_id']);

                if ($pallet->user_id !== $invoice->user_id) {
                    abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Invoice items must reference pallets owned by the invoiced user.');
                }
            }

            /** @var InvoiceItem $invoiceItem */
            $invoiceItem = InvoiceItem::query()->create([
                'invoice_id' => (int) $validated['invoice_id'],
                'pallet_id' => $validated['pallet_id'] ?? null,
                'description' => trim((string) $validated['description']),
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'billed_days' => (int) $validated['billed_days'],
                'price_per_day' => $validated['price_per_day'],
                'amount' => round((float) $validated['billed_days'] * (float) $validated['price_per_day'], 2),
                'metadata' => $validated['metadata'] ?? null,
            ]);

            $this->invoiceGenerationService->recalculateTotals($invoice);

            return $invoiceItem->load(['invoice.user.role', 'pallet.currentStatus']);
        });

        return $this->successItem($invoiceItem, 'invoice_item', 'Invoice item created successfully.', 201);
    }

    public function show(Request $request, InvoiceItem $invoiceItem): JsonResponse
    {
        $this->authorizeModule($request, 'invoice_items', 'view');
        $this->authorizeInvoiceOwnership($request, $invoiceItem->invoice()->value('user_id'));

        return $this->successItem($invoiceItem->load(['invoice.user.role', 'pallet.currentStatus']), 'invoice_item', 'Invoice item retrieved successfully.');
    }

    public function update(Request $request, InvoiceItem $invoiceItem): JsonResponse
    {
        $this->authorizeModule($request, 'invoice_items', 'update');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        $validated = $request->validate([
            'invoice_id' => ['sometimes', 'integer', 'exists:invoices,id'],
            'pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            'description' => ['sometimes', 'string', 'max:255'],
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date', 'after_or_equal:period_start'],
            'billed_days' => ['sometimes', 'integer', 'min:0'],
            'price_per_day' => ['sometimes', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        $updatedInvoiceItem = DB::transaction(function () use ($invoiceItem, $validated): InvoiceItem {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->findOrFail((int) ($validated['invoice_id'] ?? $invoiceItem->invoice_id));
            $palletId = $validated['pallet_id'] ?? $invoiceItem->pallet_id;

            if ($palletId !== null) {
                $pallet = Pallet::query()->findOrFail((int) $palletId);

                if ($pallet->user_id !== $invoice->user_id) {
                    abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Invoice items must reference pallets owned by the invoiced user.');
                }
            }

            $invoiceItem->fill([
                'invoice_id' => $invoice->id,
                'pallet_id' => $palletId,
                'description' => $validated['description'] ?? $invoiceItem->description,
                'period_start' => $validated['period_start'] ?? $invoiceItem->period_start,
                'period_end' => $validated['period_end'] ?? $invoiceItem->period_end,
                'billed_days' => (int) ($validated['billed_days'] ?? $invoiceItem->billed_days),
                'price_per_day' => $validated['price_per_day'] ?? $invoiceItem->price_per_day,
                'metadata' => $validated['metadata'] ?? $invoiceItem->metadata,
            ]);
            $invoiceItem->amount = round((float) $invoiceItem->billed_days * (float) $invoiceItem->price_per_day, 2);
            $invoiceItem->save();

            $this->invoiceGenerationService->recalculateTotals($invoice);

            return $invoiceItem->fresh(['invoice.user.role', 'pallet.currentStatus']);
        });

        return $this->successItem($updatedInvoiceItem, 'invoice_item', 'Invoice item updated successfully.');
    }

    public function destroy(Request $request, InvoiceItem $invoiceItem): JsonResponse
    {
        $this->authorizeModule($request, 'invoice_items', 'delete');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        DB::transaction(function () use ($invoiceItem): void {
            $invoice = $invoiceItem->invoice;
            $invoiceItem->delete();

            if ($invoice instanceof Invoice) {
                $this->invoiceGenerationService->recalculateTotals($invoice);
            }
        });

        return $this->success(null, 'Invoice item deleted successfully.');
    }

    private function authorizeInvoiceOwnership(Request $request, ?int $ownerId): void
    {
        if ($ownerId !== null) {
            $this->authorizeCustomerOwner($request, $ownerId, 'You are not allowed to view another customer\'s invoice items.');
        }
    }
}