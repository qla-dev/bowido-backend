<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceGenerationService;
use App\Services\InvoiceSnapshotService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends ApiController
{
    public function __construct(
        private readonly InvoiceGenerationService $invoiceGenerationService,
        private readonly InvoiceSnapshotService $invoiceSnapshotService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'invoices', 'list');
        [$limit, $offset, $filters] = $this->listParams($request, [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'invoice_number' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date'],
        ]);

        $query = Invoice::query()
            ->with(['user.role', 'user.customerDetail', 'items.pallet'])
            ->when($request->user()->isCustomer(), fn ($builder) => $builder->where('user_id', $request->user()->id))
            ->when($filters['user_id'] ?? null, fn ($builder, $value) => $builder->where('user_id', (int) $value))
            ->when($filters['invoice_number'] ?? null, fn ($builder, $value) => $builder->where('invoice_number', 'like', '%'.$value.'%'))
            ->when($filters['status'] ?? null, fn ($builder, $value) => $builder->where('status', $value))
            ->when($filters['currency'] ?? null, fn ($builder, $value) => $builder->where('currency', strtoupper((string) $value)))
            ->when($filters['period_start'] ?? null, fn ($builder, $value) => $builder->whereDate('period_start', '>=', $value))
            ->when($filters['period_end'] ?? null, fn ($builder, $value) => $builder->whereDate('period_end', '<=', $value))
            ->latest('id');

        [$items, $meta] = $this->paginateQuery($query, $limit, $offset);

        return $this->successCollection($items, 'invoice', 'Invoices retrieved successfully.', $meta);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'invoices', 'create');
        abort_if($request->user()->isCustomer(), 403, 'This action is unauthorized.');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'due_at' => ['nullable', 'date', 'after_or_equal:period_end'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer = User::query()->findOrFail((int) $validated['user_id']);
        $invoice = $this->invoiceGenerationService->generate(
            customer: $customer,
            periodStart: Carbon::parse($validated['period_start']),
            periodEnd: Carbon::parse($validated['period_end']),
            dueAt: isset($validated['due_at']) ? Carbon::parse($validated['due_at']) : null,
            currency: (string) ($validated['currency'] ?? 'EUR'),
            notes: $validated['notes'] ?? null,
        );

        return $this->successItem($invoice, 'invoice', 'Invoice created successfully.', 201);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeModule($request, 'invoices', 'view');
        $this->authorizeCustomerOwner($request, $invoice->user_id, 'You are not allowed to view another customer\'s invoice.');

        return $this->successItem($invoice->load(['user.role', 'user.customerDetail', 'items.pallet']), 'invoice', 'Invoice retrieved successfully.');
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeModule($request, 'invoices', 'update');
        abort_if($request->user()->isCustomer(), 403, 'This action is unauthorized.');

        $validated = $request->validate([
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date', 'after_or_equal:period_start'],
            'due_at' => ['nullable', 'date'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer = User::query()->findOrFail((int) ($validated['user_id'] ?? $invoice->user_id));
        $updatedInvoice = $this->invoiceGenerationService->generate(
            customer: $customer,
            periodStart: Carbon::parse($validated['period_start'] ?? $invoice->period_start),
            periodEnd: Carbon::parse($validated['period_end'] ?? $invoice->period_end),
            dueAt: array_key_exists('due_at', $validated)
                ? ($validated['due_at'] !== null ? Carbon::parse($validated['due_at']) : null)
                : ($invoice->due_at ? Carbon::parse($invoice->due_at) : null),
            currency: (string) ($validated['currency'] ?? $invoice->currency),
            notes: array_key_exists('notes', $validated) ? $validated['notes'] : $invoice->notes,
            invoice: $invoice,
            billingPeriodStart: $invoice->billing_period_start ? Carbon::parse($invoice->billing_period_start) : null,
            billingPeriodEnd: $invoice->billing_period_end ? Carbon::parse($invoice->billing_period_end) : null,
            status: $invoice->status,
        );

        return $this->successItem($updatedInvoice, 'invoice', 'Invoice updated successfully.');
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeModule($request, 'invoices', 'delete');
        abort_if($request->user()->isCustomer(), 403, 'This action is unauthorized.');
        $invoice->delete();

        return $this->success(null, 'Invoice deleted successfully.');
    }

    public function preview(User $customer, Request $request): JsonResponse
    {
        if ($request->user()->isCustomer()) {
            $this->authorizeCustomerOwner($request, $customer->id, 'You are not allowed to preview another customer\'s invoice.');
        } else {
            $this->authorizeModule($request, 'invoices', 'list');
        }

        $validated = $request->validate([
            'billing_period_start' => ['nullable', 'date'],
            'billing_period_end' => ['required', 'date', 'after_or_equal:billing_period_start'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        $preview = $this->invoiceSnapshotService->preview(
            customer: $customer,
            billingPeriodEnd: Carbon::parse($validated['billing_period_end']),
            billingPeriodStart: isset($validated['billing_period_start']) ? Carbon::parse($validated['billing_period_start']) : null,
            currency: (string) ($validated['currency'] ?? 'EUR'),
        );

        return $this->success($preview, 'Invoice preview generated successfully.');
    }

    public function sendSnapshot(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'invoices', 'create');
        abort_if($request->user()->isCustomer(), 403, 'This action is unauthorized.');

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'billing_period_start' => ['required', 'date'],
            'billing_period_end' => ['required', 'date', 'after_or_equal:billing_period_start'],
            'mark_as_sent' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        $invoice = $this->invoiceSnapshotService->sendSnapshot(
            customerId: (int) $validated['customer_id'],
            billingPeriodStart: Carbon::parse($validated['billing_period_start']),
            billingPeriodEnd: Carbon::parse($validated['billing_period_end']),
            markAsSent: (bool) ($validated['mark_as_sent'] ?? true),
            note: $validated['note'] ?? null,
            currency: (string) ($validated['currency'] ?? 'EUR'),
        );

        return $this->successItem($invoice, 'invoice', 'Invoice snapshot created successfully.', 201);
    }

    public function pdf(Invoice $invoice, Request $request): Response
    {
        $this->authorizeModule($request, 'invoices', 'view');
        $this->authorizeCustomerOwner($request, $invoice->user_id, 'You are not allowed to view another customer\'s invoice.');

        $invoice->loadMissing(['user.customerDetail', 'items.pallet']);

        return response($this->buildPdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->invoice_number.'.pdf"',
        ]);
    }

    public function invoicesExcel(Request $request): StreamedResponse
    {
        $this->authorizeModule($request, 'invoices', 'list');
        abort_if($request->user()->isCustomer(), 403, 'This action is unauthorized.');

        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $query = Invoice::query()->with(['user.customerDetail']);

        if (($validated['customer_id'] ?? null) !== null) {
            $query->where('user_id', (int) $validated['customer_id']);
        }

        if (($validated['date_from'] ?? null) !== null) {
            $query->whereDate('period_start', '>=', $validated['date_from']);
        }

        if (($validated['date_to'] ?? null) !== null) {
            $query->whereDate('period_end', '<=', $validated['date_to']);
        }

        $invoices = $query->orderBy('period_start')->get();

        return response()->streamDownload(function () use ($invoices): void {
            $stream = fopen('php://output', 'wb');

            fputcsv($stream, [
                'invoice_number',
                'customer_id',
                'customer_name',
                'billing_period_start',
                'billing_period_end',
                'period_start',
                'period_end',
                'currency',
                'total_amount',
                'status',
            ]);

            foreach ($invoices as $invoice) {
                fputcsv($stream, [
                    $invoice->invoice_number,
                    $invoice->user_id,
                    $invoice->user->customerDetail->company_name ?? $invoice->user->name,
                    $invoice->billing_period_start?->toDateString(),
                    $invoice->billing_period_end?->toDateString(),
                    $invoice->period_start?->toDateString(),
                    $invoice->period_end?->toDateString(),
                    $invoice->currency,
                    $invoice->total_amount,
                    $invoice->status,
                ]);
            }

            fclose($stream);
        }, 'invoice-report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildPdf(Invoice $invoice): string
    {
        $lines = [
            'Bowido Invoice Preview',
            'Invoice: '.$invoice->invoice_number,
            'Customer: '.($invoice->user->customerDetail->company_name ?? $invoice->user->name),
            'Billing Period: '.($invoice->billing_period_start?->toDateString() ?? $invoice->period_start?->toDateString())
                .' - '.($invoice->billing_period_end?->toDateString() ?? $invoice->period_end?->toDateString()),
            'Total: '.$invoice->currency.' '.$invoice->total_amount,
        ];

        foreach ($invoice->items as $item) {
            $lines[] = sprintf(
                '%s | days: %d | amount: %s %s',
                $item->pallet?->qr_code ?? 'N/A',
                $item->billed_days,
                $invoice->currency,
                $item->amount,
            );
        }

        $contentStream = "BT\n/F1 12 Tf\n50 760 Td\n";

        foreach (array_values($lines) as $index => $line) {
            if ($index > 0) {
                $contentStream .= "0 -18 Td\n";
            }

            $contentStream .= '(' . $this->escapePdfText($line) . ") Tj\n";
        }

        $contentStream .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n",
            "4 0 obj\n<< /Length ".strlen($contentStream)." >>\nstream\n".$contentStream."endstream\nendobj\n",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}