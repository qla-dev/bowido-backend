<?php

return [
    'default_locale' => env('APP_LOCALE', 'en'),

    'supported_locales' => ['en', 'nl', 'bs'],

    'query_parameter' => 'locale',

    'header' => 'X-Locale',

    'aliases' => [
        'en' => 'en',
        'en-us' => 'en',
        'en-gb' => 'en',
        'nl' => 'nl',
        'nl-nl' => 'nl',
        'bs' => 'bs',
        'bs-ba' => 'bs',
    ],
];
