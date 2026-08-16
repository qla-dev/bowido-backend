<?php

namespace App\Modules\Invoices\Services;

use App\Modules\InvoiceItems\Models\InvoiceItem;
use App\Modules\Invoices\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class InvoicePdfService
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['user.customerDetail', 'items.pallet']);

        $expectedReturnDates = $invoice->items->mapWithKeys(
            fn (InvoiceItem $item): array => [
                $item->id => $this->expectedReturnDate(
                    $item->metadata ?? [],
                    $item->period_start,
                    $invoice->user?->customerDetail?->grace_period_days,
                ),
            ],
        );

        return Pdf::loadView('invoices.bowido', [
            'invoice' => $invoice,
            'customer' => $invoice->user,
            'details' => $invoice->user?->customerDetail,
            'expectedReturnDates' => $expectedReturnDates,
            'language' => 'nl',
            'copy' => $this->copy(),
        ])->setPaper('a4')->output();
    }

    /**
     * Determine the date on which a pallet was due back from the customer.
     *
     * The grace period stored with an invoice item is a billing snapshot, so
     * historic invoices remain accurate if the customer's terms change later.
     */
    public function expectedReturnDate(array $metadata, mixed $periodStart, ?int $fallbackGracePeriodDays = null): ?CarbonInterface
    {
        $arrivalDate = $metadata['customer_since'] ?? $metadata['received_date'] ?? $periodStart;

        if (! $arrivalDate) {
            return null;
        }

        try {
            $gracePeriodDays = max(0, (int) ($metadata['grace_period_days'] ?? $fallbackGracePeriodDays ?? 0));

            return Carbon::parse($arrivalDate)->startOfDay()->addDays($gracePeriodDays);
        } catch (\Throwable) {
            return null;
        }
    }

    public function filename(Invoice $invoice): string
    {
        $customer = preg_replace('/[^a-z0-9]+/i', '-', $invoice->user?->customerDetail?->company_name ?? $invoice->user?->name ?? 'customer');

        return sprintf(
            'Bowido-%s-%s-%s-NL.pdf',
            trim((string) $customer, '-'),
            $invoice->invoice_number,
            $invoice->period_start->format('Y-m'),
        );
    }

    /**
     * @return array{contents: string, filename: string}
     */
    public function document(Invoice $invoice): array
    {
        return [
            'contents' => $this->render($invoice),
            'filename' => $this->filename($invoice),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function copy(): array
    {
        return [
            'title' => 'FACTUUR',
            'subtitle' => 'Transportbokretour - te late aanlevering',
            'bill_to' => 'AAN',
            'attention' => 'T.a.v.',
            'coc' => 'KvK:',
            'vat' => 'BTW:',
            'phone' => 'Tel:',
            'invoice_details' => 'FACTUURGEGEVENS',
            'invoice_number' => 'Factuurnummer:',
            'invoice_date' => 'Factuurdatum:',
            'period' => 'Periode:',
            'due_date' => 'Vervaldatum:',
            'days' => 'dagen',
            'customer_number' => 'Klantnummer:',
            'overview_title' => 'Overzicht te laat geretourneerde transportbokken',
            'overview_body' => 'Onderstaand overzicht toont de transportbokken die op de leverdatum niet binnen de toegestane grace-periode zijn geretourneerd of gemeld als gereed voor ophalen. Voor elke dag vertraging boven de grace-periode wordt :rate per transportbok per dag in rekening gebracht.',
            'number' => 'Nr.',
            'transport_reference' => 'Boknummer',
            'sent_on' => 'Verzonden op',
            'return_date' => 'Retourdatum',
            'days_overdue' => 'Dagen te laat',
            'cost' => 'Kosten',
            'not_received' => 'nog niet ontvangen',
            'no_items' => 'Geen te laat geretourneerde transportbokken in deze periode.',
            'total_crates' => 'Totaal aantal transportbokken:',
            'subtotal' => 'Subtotaal',
            'vat_compensation' => 'BTW (0% - kostenvergoeding)',
            'total_due' => 'Totaal te betalen',
            'payment_terms' => 'Betalingsvoorwaarden',
            'payment_body' => 'Gelieve het totaalbedrag binnen :days dagen na factuurdatum over te maken onder vermelding van het factuurnummer, op IBAN :iban t.n.v. :company',
            'questions' => 'Vragen over deze factuur of het transportbokkenoverzicht? Neem contact op via :email of :phone.',
            'compensation_note' => 'Deze vergoeding betreft een schadevergoeding voor te late aanlevering/retour van transportbokken en is niet onderworpen aan btw.',
        ];
    }
}
