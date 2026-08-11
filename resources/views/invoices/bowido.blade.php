<!doctype html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 45pt; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1f2a44; font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.25; }
        table { border-spacing: 0; }
        .opening { width: 100%; table-layout: fixed; }
        .opening td { vertical-align: top; }
        .company { width: 57.2%; }
        .document-heading { width: 42.8%; text-align: right; }
        .company-name { margin: 0 0 3pt; color: #1f2a44; font-size: 15pt; font-weight: 700; }
        .company-line { margin: 0 0 1pt; color: #444; font-size: 9pt; }
        .invoice-title { margin: 1pt 0 2pt; color: #1f2a44; font-size: 20pt; font-weight: 700; line-height: 1; }
        .invoice-subtitle { color: #777; font-size: 9pt; font-style: italic; }
        .party-details { width: 100%; margin-top: 13pt; table-layout: fixed; }
        .party-details td { width: 50%; vertical-align: top; text-align: left; }
        .party-details td:first-child { padding-right: 12pt; }
        .party-details td:last-child { padding-left: 12pt; }
        .section-label { margin-bottom: 3pt; color: #1f2a44; font-size: 9pt; font-weight: 700; }
        .party-name { margin-bottom: 2pt; color: #1f2a44; font-size: 10.5pt; font-weight: 700; }
        .detail-line { margin-bottom: 1pt; color: #1f2a44; font-size: 9.5pt; }
        .detail-line strong { font-weight: 700; }
        .intro { margin: 11pt 0 6pt; text-align: left; }
        .intro-title { margin: 0 0 3pt; color: #1f2a44; font-size: 11pt; font-weight: 700; }
        .intro-copy { margin: 0; color: #555; font-size: 9pt; line-height: 1.32; }
        .items { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .items thead { display: table-header-group; }
        .items th { padding: 5pt; border: 1px solid #1f2a44; background: #1f2a44; color: #fff; font-size: 8.5pt; font-weight: 700; text-align: left; }
        .items td { padding: 5pt; border: 1px solid #d9d9d9; color: #222; font-size: 9pt; font-weight: 700; vertical-align: middle; overflow-wrap: break-word; }
        .items tbody tr:nth-child(even) td { background: #f2f2f2; }
        .items .sequence, .items .overdue { text-align: center; }
        .items .overdue { color: #c0392b; }
        .items .cost { text-align: right; white-space: nowrap; }
        .items .empty { color: #555; font-weight: 400; text-align: center; }
        .items .summary td { background: #f2f2f2; color: #222; }
        .items .summary-label { text-align: left; }
        .totals { width: 45%; margin: 8pt 0 0 auto; border-collapse: collapse; page-break-inside: avoid; }
        .totals td { padding: 3pt 4pt; border-bottom: 1px solid #d9d9d9; font-size: 9.5pt; text-align: right; }
        .totals td:last-child { width: 42%; white-space: nowrap; }
        .totals .grand-total td { padding-top: 5pt; border-top: 2px solid #1f2a44; border-bottom: 0; font-size: 10.5pt; font-weight: 700; }
        .notes { margin-top: 7pt; text-align: left; }
        .notes-title { margin: 0 0 2pt; color: #1f2a44; font-size: 9pt; font-weight: 700; }
        .notes-copy { margin: 0 0 4pt; color: #1f2a44; font-size: 8.2pt; }
        .questions { margin: 0 0 4pt; color: #555; font-size: 8.2pt; }
        .vat-note { margin: 0; color: #888; font-size: 7.5pt; }
    </style>
</head>
<body>
@php
    $bowido = config('invoice.company');
    $dash = '—';
    $isDutch = $language === 'nl';
    $currencySymbol = strtoupper((string) $invoice->currency) === 'EUR' ? '€' : strtoupper((string) $invoice->currency);
    $formatDate = static function ($value) use ($dash): string {
        if (! $value) {
            return $dash;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d-m-Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    };
    $formatMoney = static function ($amount) use ($currencySymbol, $isDutch): string {
        return $currencySymbol.' '.number_format((float) $amount, 2, $isDutch ? ',' : '.', $isDutch ? '.' : ',');
    };
    $replace = static fn (string $text, array $values): string => strtr($text, $values);
    $customerName = $details?->company_name ?? $customer?->name ?? $dash;
    $contactPerson = trim((string) ($details?->contact_person ?? ''));
    $customerAddressLineOne = trim(implode(' ', array_filter([$details?->street, $details?->house_number])));
    $customerAddressLineTwo = trim(implode(' ', array_filter([$details?->postal_code, $details?->city])));
    $customerPhone = $customer?->phone_number ?? $details?->fixed_phone ?? $dash;
    $dueAt = $invoice->due_at ?: $invoice->issued_at?->copy()->addDays(14);
    $paymentDays = $invoice->issued_at && $dueAt
        ? max(0, (int) $invoice->issued_at->copy()->startOfDay()->diffInDays($dueAt, false))
        : 14;
    $dailyRate = $invoice->items->first()?->price_per_day ?? $details?->default_price_per_day ?? 0;
    $overdueDaysTotal = $invoice->items->sum(fn ($item) => (int) $item->billed_days);
    $companyIban = $bowido['iban'] ?: $dash;
    $companyAddressLineTwo = $isDutch
        ? str_ireplace([', The Netherlands', ', Netherlands'], ', Nederland', $bowido['address_line_two'])
        : str_ireplace(', Nederland', ', The Netherlands', $bowido['address_line_two']);
@endphp

<table class="opening">
    <tr>
        <td class="company">
            <div class="company-name">{{ $bowido['name'] }}</div>
            <div class="company-line">{{ $bowido['address_line_one'] }}</div>
            <div class="company-line">{{ $companyAddressLineTwo }}</div>
            <div class="company-line">{{ $copy['coc'] }} {{ $bowido['kvk'] ?: $dash }} &nbsp;·&nbsp; {{ $copy['vat'] }} {{ $bowido['vat'] ?: $dash }}</div>
            <div class="company-line">IBAN: {{ $companyIban }} &nbsp;·&nbsp; BIC: {{ $bowido['bic'] ?: $dash }}</div>
            <div class="company-line">{{ $bowido['email'] ?: $dash }} &nbsp;·&nbsp; {{ $bowido['phone'] ?: $dash }}</div>
        </td>
        <td class="document-heading">
            <div class="invoice-title">{{ $copy['title'] }}</div>
            <div class="invoice-subtitle">{{ $copy['subtitle'] }}</div>
        </td>
    </tr>
</table>

<table class="party-details">
    <tr>
        <td>
            <div class="section-label">{{ $copy['bill_to'] }}</div>
            <div class="party-name">{{ $customerName }}</div>
            <div class="detail-line">{{ $copy['attention'] }} {{ $contactPerson !== '' ? $contactPerson : $dash }}</div>
            <div class="detail-line">{{ $customerAddressLineOne !== '' ? $customerAddressLineOne : $dash }}</div>
            <div class="detail-line">{{ $customerAddressLineTwo !== '' ? $customerAddressLineTwo : $dash }}@if($details?->country), {{ $details->country }}@endif</div>
            <div class="detail-line">{{ $copy['coc'] }} {{ $details?->kvk ?? $dash }}</div>
            <div class="detail-line">{{ $copy['phone'] }} {{ $customerPhone }}</div>
        </td>
        <td>
            <div class="section-label">{{ $copy['invoice_details'] }}</div>
            <div class="detail-line"><strong>{{ $copy['invoice_number'] }}</strong> {{ $invoice->invoice_number }}</div>
            <div class="detail-line"><strong>{{ $copy['invoice_date'] }}</strong> {{ $formatDate($invoice->issued_at) }}</div>
            <div class="detail-line"><strong>{{ $copy['period'] }}</strong> {{ $formatDate($invoice->period_start) }} - {{ $formatDate($invoice->period_end) }}</div>
            <div class="detail-line"><strong>{{ $copy['due_date'] }}</strong> {{ $formatDate($dueAt) }} ({{ $paymentDays }} {{ $copy['days'] }})</div>
            <div class="detail-line"><strong>{{ $copy['customer_number'] }}</strong> {{ $customer?->id ?? $dash }}</div>
        </td>
    </tr>
</table>

<div class="intro">
    <div class="intro-title">{{ $copy['overview_title'] }}</div>
    <p class="intro-copy">{{ $replace($copy['overview_body'], [':rate' => $formatMoney($dailyRate)]) }}</p>
</div>

<table class="items">
    <colgroup>
        <col style="width: 7.5%">
        <col style="width: 20.3%">
        <col style="width: 17.1%">
        <col style="width: 17.1%">
        <col style="width: 16.6%">
        <col style="width: 21.4%">
    </colgroup>
    <thead>
        <tr>
            <th>{{ $copy['number'] }}</th>
            <th>{{ $copy['transport_reference'] }}</th>
            <th>{{ $copy['sent_on'] }}</th>
            <th>{{ $copy['return_date'] }}</th>
            <th>{{ $copy['days_overdue'] }}</th>
            <th>{{ $copy['cost'] }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($invoice->items as $item)
            @php
                $metadata = $item->metadata ?? [];
                $transportReference = $item->pallet?->pallet_name
                    ?? $item->pallet?->qr_code
                    ?? $item->pallet?->reference_code
                    ?? $item->description;
                $sentOn = $metadata['customer_since'] ?? $metadata['received_date'] ?? $item->period_start;
                $returnDate = $expectedReturnDates[$item->id] ?? null;
            @endphp
            <tr>
                <td class="sequence">{{ $loop->iteration }}</td>
                <td>{{ $transportReference }}</td>
                <td>{{ $formatDate($sentOn) }}</td>
                <td>{{ $formatDate($returnDate) }}</td>
                <td class="overdue">{{ $item->billed_days }}</td>
                <td class="cost">{{ $formatMoney($item->amount) }}</td>
            </tr>
        @empty
            <tr><td class="empty" colspan="6">{{ $copy['no_items'] }}</td></tr>
        @endforelse
        <tr class="summary">
            <td class="summary-label" colspan="4">{{ $copy['total_crates'] }} {{ $invoice->items->count() }}</td>
            <td class="sequence">{{ $overdueDaysTotal }}</td>
            <td class="cost">{{ $formatMoney($invoice->total_amount) }}</td>
        </tr>
    </tbody>
</table>

<table class="totals">
    <tr>
        <td>{{ $copy['subtotal'] }}</td>
        <td>{{ $formatMoney($invoice->subtotal_amount) }}</td>
    </tr>
    <tr>
        <td>{{ $copy['vat_compensation'] }}</td>
        <td>{{ $formatMoney(0) }}</td>
    </tr>
    <tr class="grand-total">
        <td>{{ $copy['total_due'] }}</td>
        <td>{{ $formatMoney($invoice->total_amount) }}</td>
    </tr>
</table>

<div class="notes">
    <div class="notes-title">{{ $copy['payment_terms'] }}</div>
    <p class="notes-copy">{{ $replace($copy['payment_body'], [':days' => $paymentDays, ':iban' => $companyIban, ':company' => $bowido['name']]) }}</p>
    <p class="questions">{{ $replace($copy['questions'], [':email' => $bowido['email'] ?: $dash, ':phone' => $bowido['phone'] ?: $dash]) }}</p>
    <p class="vat-note">{{ $copy['compensation_note'] }}</p>
</div>
</body>
</html>
