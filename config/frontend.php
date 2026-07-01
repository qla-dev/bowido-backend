<?php

return [
    'auto_start' => env('FRONTEND_AUTO_START', false),
    'dev_server_enabled' => env('FRONTEND_DEV_SERVER_ENABLED', false),
    'dev_server_url' => env('FRONTEND_DEV_SERVER_URL', 'http://127.0.0.1:3000'),
    'path' => env('FRONTEND_PATH', '../frontend'),
];
