<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$apply = in_array('--apply', array_slice($argv, 1), true);
$pallets = DB::table('pallets')->select(['id', 'current_location', 'metadata'])->orderBy('id')->get();
$existingStatuses = DB::table('statuses')->select(['name', 'slug'])->get();
$knownNames = [];
$knownSlugs = [];

foreach ($existingStatuses as $status) {
    $knownNames[canonicalName($status->name)] = true;
    $knownSlugs[$status->slug] = true;
}

$statusInserts = [];
$locationUpdates = [];
$metadataWithoutLocation = 0;
$invalidMetadata = 0;
$locationsClearedWithoutMetadata = 0;
$emptyLocations = 0;
$statusLabels = [];

foreach ($pallets as $pallet) {
    $metadata = decodeMetadata($pallet->metadata);

    if ($metadata === null) {
        $invalidMetadata++;

        if ($pallet->current_location !== null) {
            $locationUpdates[] = [
                'id' => $pallet->id,
                'current_location' => null,
            ];
            $locationsClearedWithoutMetadata++;
        }

        continue;
    }

    $where = metadataValue($metadata, 'WAAR');

    if ($where !== null && trim($where) !== '') {
        $name = trim($where);
        $statusLabels[$name] = true;
        $canonicalName = canonicalName($name);

        if (! isset($knownNames[$canonicalName])) {
            $slug = uniqueSlug($name, $knownSlugs);
            $statusInserts[] = [
                'name' => $name,
                'slug' => $slug,
            ];
            $knownNames[$canonicalName] = true;
            $knownSlugs[$slug] = true;
        }
    }

    $location = metadataValue($metadata, 'LOCATIE');

    if ($location === null) {
        $metadataWithoutLocation++;

        continue;
    }

    if ($location === '') {
        $emptyLocations++;
    }

    if ((string) $pallet->current_location !== $location) {
        $locationUpdates[] = [
            'id' => $pallet->id,
            'current_location' => $location,
        ];
    }
}

$result = [
    'mode' => $apply ? 'apply' : 'dry_run',
    'pallets_checked' => $pallets->count(),
    'distinct_waar_labels' => count($statusLabels),
    'statuses_to_insert' => count($statusInserts),
    'status_inserts' => $statusInserts,
    'location_updates_needed' => count($locationUpdates),
    'blank_locations_from_metadata' => $emptyLocations,
    'metadata_without_location' => $metadataWithoutLocation,
    'invalid_or_empty_metadata' => $invalidMetadata,
    'locations_cleared_without_metadata' => $locationsClearedWithoutMetadata,
];

if ($apply) {
    DB::transaction(function () use ($statusInserts, $locationUpdates): void {
        $now = now();
        $sortOrder = (int) DB::table('statuses')->max('sort_order');

        foreach ($statusInserts as $status) {
            $sortOrder += 10;

            DB::table('statuses')->insert([
                'name' => $status['name'],
                'slug' => $status['slug'],
                'is_billable' => false,
                'is_active' => true,
                'sort_order' => $sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($locationUpdates as $update) {
            DB::table('pallets')
                ->where('id', $update['id'])
                ->update([
                    'current_location' => $update['current_location'],
                    'updated_at' => $now,
                ]);
        }
    });

    $result['statuses_inserted'] = count($statusInserts);
    $result['locations_updated'] = count($locationUpdates);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

function decodeMetadata(mixed $metadata): ?array
{
    if (! is_string($metadata) || trim($metadata) === '') {
        return null;
    }

    try {
        $decoded = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }

    return is_array($decoded) ? $decoded : null;
}

function metadataValue(array $metadata, string $key): ?string
{
    if (array_key_exists($key, $metadata)) {
        return valueToString($metadata[$key]);
    }

    if (isset($metadata['csv']) && is_array($metadata['csv']) && array_key_exists($key, $metadata['csv'])) {
        return valueToString($metadata['csv'][$key]);
    }

    return null;
}

function valueToString(mixed $value): ?string
{
    if ($value === null || is_array($value) || is_object($value)) {
        return null;
    }

    return trim((string) $value);
}

function canonicalName(string $name): string
{
    return Str::of($name)->squish()->lower()->value();
}

function uniqueSlug(string $name, array $knownSlugs): string
{
    $base = Str::slug($name);
    $base = $base !== '' ? $base : 'status';
    $slug = $base;
    $suffix = 2;

    while (isset($knownSlugs[$slug])) {
        $slug = "{$base}-{$suffix}";
        $suffix++;
    }

    return $slug;
}
