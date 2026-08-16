<?php

/**
 * Mark every existing customer account as a first-time login.
 *
 * Browser: /reset-customer-first-login.php
 * CLI:     php public/reset-customer-first-login.php
 *
 * This only updates the first_time_login flag for users with the "customer"
 * role. It does not change passwords, customer details, or any other account
 * data. Customers will see the first-login flow the next time they load the
 * application.
 */

declare(strict_types=1);

use App\Modules\Users\Models\User;
use Illuminate\Contracts\Http\Kernel;

require dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

$app = require dirname(__DIR__).DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
$app->make(Kernel::class)->bootstrap();

$customerQuery = User::query()->whereHas('role', function ($query): void {
    $query->where('name', 'customer');
});

$customerCount = (clone $customerQuery)->count();
$updatedCount = $customerQuery->update(['first_time_login' => true]);

$message = sprintf(
    'Marked %d of %d customer account(s) for first login.',
    $updatedCount,
    $customerCount,
);

if (PHP_SAPI === 'cli') {
    echo $message.PHP_EOL;
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo $message."\n";
echo 'Their passwords and registration details were not changed.' . "\n";
