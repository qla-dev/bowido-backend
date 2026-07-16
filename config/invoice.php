<?php

return [
    'company_address' => env('BOWIDO_INVOICE_ADDRESS', ''),
    'company_email' => env('BOWIDO_INVOICE_EMAIL', env('MAIL_FROM_ADDRESS')),
];
