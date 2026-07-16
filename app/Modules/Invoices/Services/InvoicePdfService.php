<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdfService
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['user.customerDetail', 'items.pallet']);

        return Pdf::loadView('invoices.bowido', [
            'invoice' => $invoice,
            'customer' => $invoice->user,
            'details' => $invoice->user?->customerDetail,
            'logo' => 'data:image/svg+xml;base64,'.base64_encode((string) file_get_contents(public_path('images/bowido-logo.svg'))),
        ])->setPaper('a4')->output();
    }

    public function filename(Invoice $invoice): string
    {
        $customer = preg_replace('/[^a-z0-9]+/i', '-', $invoice->user?->customerDetail?->company_name ?? $invoice->user?->name ?? 'customer');

        return sprintf('Bowido-%s-%s-%s.pdf', trim((string) $customer, '-'), $invoice->invoice_number, $invoice->period_start->format('Y-m'));
    }
}
