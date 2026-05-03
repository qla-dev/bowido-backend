<?php

namespace App\Modules\Invoices\Controllers;

use App\Modules\Invoices\DTOs\InvoiceData;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Requests\ListInvoicesRequest;
use App\Modules\Invoices\Requests\StoreInvoiceRequest;
use App\Modules\Invoices\Requests\UpdateInvoiceRequest;
use App\Modules\Invoices\Resources\InvoiceResource;
use App\Modules\Invoices\Services\InvoiceService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class InvoiceController extends ApiController
{
    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    public function index(ListInvoicesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        return $this->successCollection(
            $this->invoiceService->paginate(ListQueryData::fromRequest($request), $request->user()),
            InvoiceResource::class,
            'Invoices retrieved successfully.',
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $invoice = $this->invoiceService->create(InvoiceData::fromArray($request->validated()));

        return $this->successItem($invoice, InvoiceResource::class, 'Invoice created successfully.', 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return $this->successItem(
            $this->invoiceService->find($invoice->id, request()->user()),
            InvoiceResource::class,
            'Invoice retrieved successfully.',
        );
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $updatedInvoice = $this->invoiceService->update($invoice, InvoiceData::fromArray([
            ...$invoice->toArray(),
            ...$request->validated(),
        ]));

        return $this->successItem($updatedInvoice, InvoiceResource::class, 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        $this->invoiceService->delete($invoice->id, request()->user());

        return $this->success(null, 'Invoice deleted successfully.');
    }
}
