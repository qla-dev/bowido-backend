<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$rows = DB::table('pallets')
    ->select(['id', 'qr_code', 'pallet_name'])
    ->orderBy('id')
    ->get();

$targets = [];
$updates = [];

foreach ($rows as $row) {
    $target = normalizePalletCode($row->pallet_name, $row->qr_code);

    if ($target === '') {
        throw new RuntimeException("Pallet id {$row->id} does not have a usable QR code or pallet name.");
    }

    if (isset($targets[$target])) {
        throw new RuntimeException("Duplicate normalized QR code '{$target}' for pallet ids {$targets[$target]} and {$row->id}.");
    }

    $targets[$target] = $row->id;

    if ((string) $row->qr_code !== $target || (string) ($row->pallet_name ?? '') !== $target) {
        $updates[] = [
            'id' => (int) $row->id,
            'target' => $target,
        ];
    }
}

DB::transaction(function () use ($updates): void {
    $now = now();

    foreach ($updates as $update) {
        DB::table('pallets')
            ->where('id', $update['id'])
            ->update([
                'qr_code' => '__NORMALIZING_QR__' . $update['id'] . '__' . Str::random(12),
                'updated_at' => $now,
            ]);
    }

    foreach ($updates as $update) {
        DB::table('pallets')
            ->where('id', $update['id'])
            ->update([
                'qr_code' => $update['target'],
                'pallet_name' => $update['target'],
                'updated_at' => $now,
            ]);
    }
});

echo json_encode([
    'checked' => $rows->count(),
    'updated' => count($updates),
    'unchanged' => $rows->count() - count($updates),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

function normalizePalletCode(?string $preferred, ?string $fallback): string
{
    foreach ([$preferred, $fallback] as $value) {
        $base = trim(explode(';', (string) $value)[0]);

        if ($base !== '') {
            return Str::of($base)
                ->upper()
                ->replaceMatches('/\s+/', '')
                ->value();
        }
    }

    return '';
}
