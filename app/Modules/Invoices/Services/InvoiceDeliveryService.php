<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\Mail\BowidoInvoiceMail;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Shared\Enums\InvoiceStatus;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvoiceDeliveryService
{
    public function __construct(private readonly InvoicePdfService $pdfService) {}

    public function recipientFor(User $customer): ?string
    {
        $customer->loadMissing('customerDetail');
        $recipient = $customer->customerDetail?->billing_email ?? $customer->email;

        return filter_var($recipient, FILTER_VALIDATE_EMAIL) ? $recipient : null;
    }

    public function send(Invoice $invoice, string $source = 'manual_invoice'): string
    {
        $invoice->loadMissing('user.customerDetail');
        $recipient = $invoice->user instanceof User ? $this->recipientFor($invoice->user) : null;

        if ($recipient === null) {
            throw new \InvalidArgumentException(__('The customer does not have a valid invoice email address.'));
        }

        Log::info('Invoice email delivery started.', [
            'source' => $source,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'recipient' => $recipient,
        ]);

        $pdf = $this->pdfService->render($invoice);
        Mail::to($recipient)->send(new BowidoInvoiceMail($invoice, $pdf, $this->pdfService->filename($invoice)));
        $invoice->forceFill(['status' => InvoiceStatus::Sent->value])->save();

        Log::info('Invoice email delivered.', [
            'source' => $source,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'recipient' => $recipient,
        ]);

        return $recipient;
    }
}
