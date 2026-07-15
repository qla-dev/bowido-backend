<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$apply = in_array('--apply', array_slice($argv, 1), true);
$records = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);

if (! is_array($records) || $records === []) {
    throw new RuntimeException('No decoded QR records were provided.');
}

$byPalletCode = [];
$incomingQrToPalletCode = [];

foreach ($records as $record) {
    if (! is_array($record)) {
        throw new RuntimeException('Every decoded QR record must be an object.');
    }

    $palletCode = strtoupper(trim((string) ($record['pallet_code'] ?? '')));
    $referenceCode = trim((string) ($record['reference_code'] ?? ''));
    $qrCode = trim((string) ($record['qr_code'] ?? ''));

    if (! preg_match('/^BOWNL-\d{4}$/', $palletCode) || $referenceCode === '' || $qrCode === '') {
        throw new RuntimeException('A decoded QR record is missing a valid pallet code, reference code, or QR payload.');
    }

    if (isset($byPalletCode[$palletCode])) {
        throw new RuntimeException("Duplicate pallet code in QR archives: {$palletCode}");
    }

    if (isset($incomingQrToPalletCode[$qrCode])) {
        throw new RuntimeException("Duplicate decoded QR payload in QR archives: {$qrCode}");
    }

    $byPalletCode[$palletCode] = [
        'reference_code' => $referenceCode,
        'qr_code' => $qrCode,
    ];
    $incomingQrToPalletCode[$qrCode] = $palletCode;
}

$matched = [];
$duplicateMatches = [];

foreach (DB::table('pallets')->select(['id', 'pallet_name', 'reference_code', 'qr_code'])->orderBy('id')->get() as $row) {
    if (! preg_match('/BOWNL-\d{4}/i', (string) $row->pallet_name, $matches)) {
        continue;
    }

    $palletCode = strtoupper($matches[0]);

    if (! isset($byPalletCode[$palletCode])) {
        continue;
    }

    if (isset($matched[$palletCode])) {
        $duplicateMatches[$palletCode] = [$matched[$palletCode]->id, $row->id];
        continue;
    }

    $matched[$palletCode] = $row;
}

$missingPallets = array_values(array_diff(array_keys($byPalletCode), array_keys($matched)));
$qrConflicts = DB::table('pallets')
    ->whereIn('qr_code', array_keys($incomingQrToPalletCode))
    ->get(['id', 'qr_code'])
    ->filter(function (object $row) use ($incomingQrToPalletCode, $matched): bool {
        $palletCode = $incomingQrToPalletCode[$row->qr_code];

        return ! isset($matched[$palletCode]) || $matched[$palletCode]->id !== $row->id;
    })
    ->values()
    ->all();

$updates = [];

foreach ($matched as $palletCode => $row) {
    $record = $byPalletCode[$palletCode];

    if ($row->reference_code === $record['reference_code'] && $row->qr_code === $record['qr_code']) {
        continue;
    }

    $updates[] = [
        'id' => $row->id,
        'pallet_code' => $palletCode,
        'reference_code' => $record['reference_code'],
        'qr_code' => $record['qr_code'],
    ];
}

$result = [
    'mode' => $apply ? 'apply' : 'dry_run',
    'decoded_records' => count($byPalletCode),
    'matched_pallets' => count($matched),
    'updates_needed' => count($updates),
    'already_current' => count($matched) - count($updates),
    'missing_pallets' => $missingPallets,
    'skipped_missing' => count($missingPallets),
    'duplicate_database_matches' => $duplicateMatches,
    'qr_conflicts' => $qrConflicts,
    'sample_updates' => array_slice($updates, 0, 3),
];

if ($duplicateMatches !== [] || $qrConflicts !== []) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(2);
}

if ($apply) {
    DB::transaction(function () use ($updates): void {
        $now = now();

        foreach ($updates as $update) {
            DB::table('pallets')
                ->where('id', $update['id'])
                ->update([
                    'reference_code' => $update['reference_code'],
                    'qr_code' => $update['qr_code'],
                    'updated_at' => $now,
                ]);
        }
    });

    $result['updated'] = count($updates);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
