<?php

return [
    'company' => [
        'name' => env('BOWIDO_INVOICE_NAME', 'BoWiDo Nederland B.V.'),
        'kvk' => env('BOWIDO_INVOICE_KVK', '82860734'),
        'vat' => env('BOWIDO_INVOICE_VAT'),
        'iban' => env('BOWIDO_INVOICE_IBAN'),
        'bic' => env('BOWIDO_INVOICE_BIC'),
        'address_line_one' => env('BOWIDO_INVOICE_ADDRESS_LINE_ONE', 'Maxwellstraat 2-4'),
        'address_line_two' => env('BOWIDO_INVOICE_ADDRESS_LINE_TWO', '3316 GP Dordrecht, Netherlands'),
        'contact_person' => env('BOWIDO_INVOICE_CONTACT_PERSON', 'BoWiDo sales team'),
        'phone' => env('BOWIDO_INVOICE_PHONE', '+31 85 049 17 52'),
        'email' => env('BOWIDO_INVOICE_EMAIL', 'info@bowido.nl'),
    ],
];
