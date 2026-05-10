<?php

namespace App\Modules\InvoiceItems\Controllers;

use App\Modules\InvoiceItems\DTOs\InvoiceItemData;
use App\Modules\InvoiceItems\Models\InvoiceItem;
use App\Modules\InvoiceItems\Requests\ListInvoiceItemsRequest;
use App\Modules\InvoiceItems\Requests\StoreInvoiceItemRequest;
use App\Modules\InvoiceItems\Requests\UpdateInvoiceItemRequest;
use App\Modules\InvoiceItems\Resources\InvoiceItemResource;
use App\Modules\InvoiceItems\Services\InvoiceItemService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class InvoiceItemController extends ApiController
{
    public function __construct(private readonly InvoiceItemService $invoiceItemService)
    {
    }

    public function index(ListInvoiceItemsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', InvoiceItem::class);

        return $this->successCollection(
            $this->invoiceItemService->paginate(ListQueryData::fromRequest($request), $request->user()),
            InvoiceItemResource::class,
            __('Invoice items retrieved successfully.'),
        );
    }

    public function store(StoreInvoiceItemRequest $request): JsonResponse
    {
        $this->authorize('create', InvoiceItem::class);

        $invoiceItem = $this->invoiceItemService->create(InvoiceItemData::fromArray($request->validated()));

        return $this->successItem($invoiceItem, InvoiceItemResource::class, __('Invoice item created successfully.'), 201);
    }

    public function show(InvoiceItem $invoiceItem): JsonResponse
    {
        $this->authorize('view', $invoiceItem);

        return $this->successItem(
            $this->invoiceItemService->find($invoiceItem->id, request()->user()),
            InvoiceItemResource::class,
            __('Invoice item retrieved successfully.'),
        );
    }

    public function update(UpdateInvoiceItemRequest $request, InvoiceItem $invoiceItem): JsonResponse
    {
        $this->authorize('update', $invoiceItem);

        $updatedInvoiceItem = $this->invoiceItemService->update($invoiceItem, InvoiceItemData::fromArray([
            ...$invoiceItem->toArray(),
            ...$request->validated(),
        ]));

        return $this->successItem($updatedInvoiceItem, InvoiceItemResource::class, __('Invoice item updated successfully.'));
    }

    public function destroy(InvoiceItem $invoiceItem): JsonResponse
    {
        $this->authorize('delete', $invoiceItem);

        $this->invoiceItemService->delete($invoiceItem->id, request()->user());

        return $this->success(null, __('Invoice item deleted successfully.'));
    }
}
