<?php

return [
    'provider' => env('LOCATION_PROVIDER', 'geoapify'),

    'cache' => [
        'ttl_seconds' => (int) env('LOCATION_CACHE_TTL_SECONDS', 2592000),
        'coordinate_precision' => (int) env('LOCATION_CACHE_COORDINATE_PRECISION', 5),
    ],

    'rate_limit_per_minute' => (int) env('LOCATION_RATE_LIMIT_PER_MINUTE', 30),

    'geoapify' => [
        'api_key' => env('GEOAPIFY_API_KEY'),
        'base_url' => env('GEOAPIFY_BASE_URL', 'https://api.geoapify.com'),
        'timeout_seconds' => (int) env('GEOAPIFY_TIMEOUT_SECONDS', 8),
        'connect_timeout_seconds' => (int) env('GEOAPIFY_CONNECT_TIMEOUT_SECONDS', 3),
    ],
];
