<?php

return [
    'disk' => env('PALLET_PHOTO_DISK', env('FILESYSTEM_DISK', 'local')),
    'retention_months' => (int) env('PALLET_PHOTO_RETENTION_MONTHS', 3),
    'temporary_url_minutes' => (int) env('PALLET_PHOTO_URL_MINUTES', 10),
];
