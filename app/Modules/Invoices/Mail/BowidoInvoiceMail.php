<?php

namespace App\Modules\Invoices\Mail;

use App\Modules\Invoices\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BowidoInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice, private readonly string $pdf, private readonly string $filename) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('BoWiDo factuur :number', ['number' => $this->invoice->invoice_number]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invoice');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn (): string => $this->pdf, $this->filename)->withMime('application/pdf')];
    }
}
