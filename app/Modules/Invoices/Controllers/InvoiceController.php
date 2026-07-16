<?php

namespace App\Modules\Invoices\Controllers;

use App\Modules\Invoices\DTOs\InvoiceData;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Requests\ListInvoicesRequest;
use App\Modules\Invoices\Requests\StoreInvoiceRequest;
use App\Modules\Invoices\Requests\UpdateInvoiceRequest;
use App\Modules\Invoices\Resources\InvoiceResource;
use App\Modules\Invoices\Services\InvoiceDeliveryService;
use App\Modules\Invoices\Services\InvoicePdfService;
use App\Modules\Invoices\Services\InvoiceService;
use App\Modules\Invoices\Services\OverduePalletInvoiceService;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends ApiController
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoicePdfService $pdfService,
        private readonly InvoiceDeliveryService $deliveryService,
        private readonly OverduePalletInvoiceService $overduePalletInvoiceService,
    ) {}

    public function index(ListInvoicesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        return $this->successCollection(
            $this->invoiceService->paginate(ListQueryData::fromRequest($request), $request->user()),
            InvoiceResource::class,
            __('Invoices retrieved successfully.'),
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $invoice = $this->invoiceService->create(InvoiceData::fromArray($request->validated()));

        return $this->successItem($invoice, InvoiceResource::class, __('Invoice created successfully.'), 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return $this->successItem(
            $this->invoiceService->find($invoice->id, request()->user()),
            InvoiceResource::class,
            __('Invoice retrieved successfully.'),
        );
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $updatedInvoice = $this->invoiceService->update($invoice, InvoiceData::fromArray([
            ...$invoice->toArray(),
            ...$request->validated(),
        ]));

        return $this->successItem($updatedInvoice, InvoiceResource::class, __('Invoice updated successfully.'));
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        $this->invoiceService->delete($invoice->id, request()->user());

        return $this->success(null, __('Invoice deleted successfully.'));
    }

    public function preview(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        $pdf = $this->pdfService->render($invoice);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->pdfService->filename($invoice).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function download(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return response($this->pdfService->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->pdfService->filename($invoice).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function send(Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);
        try {
            $recipient = $this->deliveryService->send($invoice);
        } catch (\InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->success(['recipient' => $recipient], __('Invoice sent successfully.'));
    }

    public function sendOverduePalletInvoice(Pallet $pallet): JsonResponse
    {
        $this->authorize('update', $pallet);

        try {
            $result = $this->overduePalletInvoiceService->sendForDashboard($pallet);
        } catch (\InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->success([
            'invoice_id' => $result['invoice']->id,
            'recipient' => $result['recipient'],
        ], __('Overdue pallet invoice sent successfully.'));
    }
}
