<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$apply = in_array('--apply', array_slice($argv, 1), true);
$legacyStatusIds = [1, 2, 3, 4, 5, 6];
$targetSlugByWhere = [
    'bowido nl' => 'bowido-nl',
    'bij de klant' => 'bij-de-klant',
    'ophalen klant' => 'ophalen-klant',
    'bih-nl transport' => 'bih-nl-transport',
    'bowido bih' => 'bowido-bih',
    'nl-bih transport' => 'nl-bih-transport',
    'onbekend' => 'onbekend',
    'bih-drugo' => 'bih-drugo',
];
$fallbackSlugByLegacyId = [
    3 => 'bij-de-klant',
    4 => 'ophalen-klant',
    5 => 'bih-drugo',
    6 => 'onbekend',
];

$targetStatusIds = DB::table('statuses')
    ->whereIn('slug', array_values($targetSlugByWhere))
    ->pluck('id', 'slug')
    ->all();

$missingTargetSlugs = array_values(array_diff(array_values($targetSlugByWhere), array_keys($targetStatusIds)));

if ($missingTargetSlugs !== []) {
    throw new RuntimeException('Required target statuses are missing: '.implode(', ', $missingTargetSlugs));
}

$palletTargetIds = [];
$palletUpdates = [];
$unmappedPallets = [];

foreach (DB::table('pallets')->select(['id', 'current_status_id', 'metadata'])->orderBy('id')->get() as $pallet) {
    $targetSlug = targetSlugFromMetadata($pallet->metadata, $targetSlugByWhere)
        ?? ($fallbackSlugByLegacyId[(int) $pallet->current_status_id] ?? null);

    if ($targetSlug !== null) {
        $palletTargetIds[$pallet->id] = $targetStatusIds[$targetSlug];
    }

    if (! in_array((int) $pallet->current_status_id, $legacyStatusIds, true)) {
        continue;
    }

    if ($targetSlug === null) {
        $unmappedPallets[] = $pallet->id;

        continue;
    }

    $palletUpdates[] = [
        'id' => $pallet->id,
        'current_status_id' => $targetStatusIds[$targetSlug],
    ];
}

$auditUpdates = [];
$unmappedAuditReferences = [];

foreach (DB::table('audit_logs')
    ->select(['id', 'pallet_id', 'old_status_id', 'new_status_id'])
    ->where(function ($query) use ($legacyStatusIds): void {
        $query->whereIn('old_status_id', $legacyStatusIds)
            ->orWhereIn('new_status_id', $legacyStatusIds);
    })
    ->orderBy('id')
    ->get() as $auditLog) {
    $update = [];

    foreach (['old_status_id', 'new_status_id'] as $column) {
        $legacyStatusId = $auditLog->{$column};

        if ($legacyStatusId === null || ! in_array((int) $legacyStatusId, $legacyStatusIds, true)) {
            continue;
        }

        $targetStatusId = $palletTargetIds[$auditLog->pallet_id]
            ?? (isset($fallbackSlugByLegacyId[(int) $legacyStatusId])
                ? $targetStatusIds[$fallbackSlugByLegacyId[(int) $legacyStatusId]]
                : null);

        if ($targetStatusId === null) {
            $unmappedAuditReferences[] = [
                'audit_log_id' => $auditLog->id,
                'pallet_id' => $auditLog->pallet_id,
                'column' => $column,
                'legacy_status_id' => $legacyStatusId,
            ];

            continue;
        }

        $update[$column] = $targetStatusId;
    }

    if ($update !== []) {
        $auditUpdates[] = [
            'id' => $auditLog->id,
            ...$update,
        ];
    }
}

$result = [
    'mode' => $apply ? 'apply' : 'dry_run',
    'legacy_status_ids' => $legacyStatusIds,
    'target_status_ids' => $targetStatusIds,
    'pallet_updates_needed' => count($palletUpdates),
    'audit_log_updates_needed' => count($auditUpdates),
    'unmapped_pallet_ids' => $unmappedPallets,
    'unmapped_audit_references' => $unmappedAuditReferences,
];

if ($unmappedPallets !== [] || $unmappedAuditReferences !== []) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(2);
}

if ($apply) {
    DB::transaction(function () use ($palletUpdates, $auditUpdates, $legacyStatusIds): void {
        $now = now();

        foreach ($palletUpdates as $update) {
            DB::table('pallets')
                ->where('id', $update['id'])
                ->update([
                    'current_status_id' => $update['current_status_id'],
                    'updated_at' => $now,
                ]);
        }

        foreach ($auditUpdates as $update) {
            $values = array_filter([
                'old_status_id' => $update['old_status_id'] ?? null,
                'new_status_id' => $update['new_status_id'] ?? null,
            ], static fn (mixed $value): bool => $value !== null);
            $values['updated_at'] = $now;

            DB::table('audit_logs')->where('id', $update['id'])->update($values);
        }

        $remainingPalletReferences = DB::table('pallets')
            ->whereIn('current_status_id', $legacyStatusIds)
            ->count();
        $remainingAuditReferences = DB::table('audit_logs')
            ->where(function ($query) use ($legacyStatusIds): void {
                $query->whereIn('old_status_id', $legacyStatusIds)
                    ->orWhereIn('new_status_id', $legacyStatusIds);
            })
            ->count();

        if ($remainingPalletReferences !== 0 || $remainingAuditReferences !== 0) {
            throw new RuntimeException('Legacy status references remain after remapping.');
        }

        DB::table('statuses')->whereIn('id', $legacyStatusIds)->delete();
    });

    $result['pallets_updated'] = count($palletUpdates);
    $result['audit_logs_updated'] = count($auditUpdates);
    $result['legacy_statuses_deleted'] = count($legacyStatusIds);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

/**
 * @param array<string, string> $targetSlugByWhere
 */
function targetSlugFromMetadata(?string $metadata, array $targetSlugByWhere): ?string
{
    if ($metadata === null || trim($metadata) === '') {
        return null;
    }

    try {
        $decoded = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }

    if (! is_array($decoded)) {
        return null;
    }

    $where = $decoded['WAAR']
        ?? $decoded['csv']['WAAR']
        ?? $decoded['status_label']
        ?? null;

    if (! is_scalar($where) || trim((string) $where) === '') {
        return null;
    }

    return $targetSlugByWhere[Str::of((string) $where)->squish()->lower()->value()] ?? null;
}
