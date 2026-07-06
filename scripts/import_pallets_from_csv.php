<?php

declare(strict_types=1);

use App\Modules\Shared\Support\Normalizer;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$defaultPath = storage_path('app/imports/pallet_tracking.csv');
$args = array_values(array_slice($argv, 1));
$dryRun = in_array('--dry-run', $args, true);
$overwrite = in_array('--overwrite', $args, true);
$filePath = collect($args)->first(fn (string $arg): bool => ! str_starts_with($arg, '--')) ?? $defaultPath;

if (! is_string($filePath) || ! is_file($filePath)) {
    fwrite(STDERR, "CSV file not found: {$filePath}\n");
    exit(1);
}

if (! $dryRun && ! $overwrite) {
    fwrite(STDERR, "Refusing to replace pallets without --overwrite. Run with --dry-run first if you want a preview.\n");
    exit(1);
}

$importedAt = now();
$columnAdditions = ensureImportColumns($dryRun);
$rows = parseCsv($filePath);

if ($rows === []) {
    fwrite(STDERR, "No pallet rows were found in {$filePath}.\n");
    exit(1);
}

$duplicates = duplicateQrCodes($rows);

if ($duplicates !== []) {
    fwrite(STDERR, 'Duplicate BOKNUMMER values in CSV: '.implode(', ', $duplicates)."\n");
    exit(1);
}

$statuses = loadStatusLookup();
$customerCache = loadCustomerLookup();
$adminUserId = DB::table('users')->where('email', 'admin@example.com')->value('id');
$customerRoleId = DB::table('roles')->where('name', 'customer')->value('id');

if ($customerRoleId === null) {
    fwrite(STDERR, "Customer role was not found.\n");
    exit(1);
}

$billingDefaults = customerBillingDefaults();
$preview = buildImportPreview($rows, $statuses);
$oldCounts = [
    'pallets' => DB::table('pallets')->count(),
    'audit_logs' => DB::table('audit_logs')->count(),
    'service_reports' => DB::table('service_reports')->count(),
    'invoice_items_linked_to_pallets' => DB::table('invoice_items')->whereNotNull('pallet_id')->count(),
    'ghost_reports_linked_to_pallets' => DB::table('ghost_pallet_reports')->whereNotNull('paired_pallet_id')->count(),
];

$results = [
    'source_file' => $filePath,
    'dry_run' => $dryRun,
    'overwrite' => $overwrite,
    'old_counts' => $oldCounts,
    'columns_added' => $columnAdditions,
    'csv_rows' => count($rows),
    'pallets_to_import' => count($preview),
    'status_counts' => statusCounts($preview),
    'customers_to_create' => countCustomersToCreate($preview, $customerCache),
    'created_customers' => 0,
    'deleted' => [
        'pallets' => 0,
        'audit_logs' => 0,
        'service_reports' => 0,
        'invoice_item_links_cleared' => 0,
        'ghost_report_links_cleared' => 0,
    ],
    'inserted_pallets' => 0,
    'inserted_audit_logs' => 0,
    'skipped_rows' => count($rows) - count($preview),
];

if ($dryRun) {
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
    exit(0);
}

DB::transaction(function () use (
    $preview,
    $statuses,
    &$customerCache,
    $customerRoleId,
    $billingDefaults,
    $importedAt,
    $filePath,
    $adminUserId,
    &$results,
): void {
    $palletIds = DB::table('pallets')->pluck('id');

    if ($palletIds->isNotEmpty()) {
        $results['deleted']['audit_logs'] = DB::table('audit_logs')
            ->whereIn('pallet_id', $palletIds)
            ->delete();

        $results['deleted']['service_reports'] = DB::table('service_reports')
            ->whereIn('pallet_id', $palletIds)
            ->delete();

        $results['deleted']['invoice_item_links_cleared'] = DB::table('invoice_items')
            ->whereIn('pallet_id', $palletIds)
            ->update(['pallet_id' => null, 'updated_at' => $importedAt]);

        $results['deleted']['ghost_report_links_cleared'] = DB::table('ghost_pallet_reports')
            ->whereIn('paired_pallet_id', $palletIds)
            ->update(['paired_pallet_id' => null, 'updated_at' => $importedAt]);
    }

    $results['deleted']['pallets'] = DB::table('pallets')->delete();

    foreach ($preview as $item) {
        $row = $item['row'];
        $customerName = customerNameForRow($row);
        $userId = getOrCreateCustomerId(
            customerName: $customerName,
            customerCache: $customerCache,
            customerRoleId: (int) $customerRoleId,
            billingDefaults: $billingDefaults,
            row: $row,
            createdCustomers: $results['created_customers'],
            now: $importedAt,
        );

        $statusId = $statuses[$item['status_slug']] ?? null;

        if ($statusId === null) {
            throw new RuntimeException("Status '{$item['status_slug']}' was not found for pallet {$item['qr_code']}.");
        }

        $lastStatusChangedAt = parseDate(firstFilled($row, ['verzonden', 'ophalen_gemeld_op']));
        $daysAtCustomer = daysAtCustomer($item['status_slug'], $lastStatusChangedAt);
        $overdueDays = overdueDays($row['terug'] ?? '');
        $currentLocation = currentLocation($row);
        $metadata = [
            'source' => 'csv_import',
            'source_file' => basename($filePath),
            'imported_at' => $importedAt->toDateTimeString(),
            'csv' => $row['raw'],
            'status_label' => $row['waar'] ?? null,
            'customer_label' => $row['klant'] ?? null,
            'return_label' => $row['terug'] ?? null,
            'qr_image' => $row['qr_code'] ?? null,
            'how_to_scan' => $row['how_to_scan'] ?? null,
        ];

        $palletId = DB::table('pallets')->insertGetId([
            'user_id' => $userId,
            'current_status_id' => $statusId,
            'type' => palletType($row['type'] ?? ''),
            'asset_type' => 'pallet',
            'qr_code' => $item['qr_code'],
            'pallet_name' => $item['qr_code'],
            'reference_code' => $item['qr_code'],
            'current_location' => $currentLocation,
            'notes' => nullableString($row['opmerking'] ?? null),
            'last_status_changed_at' => $lastStatusChangedAt,
            'days_at_customer' => $daysAtCustomer,
            'grace_days' => null,
            'overdue_days' => $overdueDays,
            'debt_eur' => null,
            'is_active' => true,
            'is_ghost' => false,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $importedAt,
            'updated_at' => $importedAt,
        ]);

        $auditDate = $lastStatusChangedAt ?? $importedAt->toDateTimeString();

        DB::table('audit_logs')->insert([
            'pallet_id' => $palletId,
            'made_by_user_id' => $adminUserId,
            'event_type' => 'status_changed',
            'note' => 'Imported from pallet CSV.',
            'old_status_id' => null,
            'new_status_id' => $statusId,
            'old_client_id' => null,
            'new_client_id' => $userId,
            'old_location' => null,
            'new_location' => $currentLocation,
            'qr_code_version' => null,
            'old_qr_code' => null,
            'new_qr_code' => $item['qr_code'],
            'context' => json_encode([
                'source' => 'csv_import',
                'source_file' => basename($filePath),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $auditDate,
            'updated_at' => $auditDate,
        ]);

        $results['inserted_pallets']++;
        $results['inserted_audit_logs']++;
    }
});

$results['new_counts'] = [
    'pallets' => DB::table('pallets')->count(),
    'audit_logs' => DB::table('audit_logs')->count(),
    'service_reports' => DB::table('service_reports')->count(),
    'invoice_items_linked_to_pallets' => DB::table('invoice_items')->whereNotNull('pallet_id')->count(),
    'ghost_reports_linked_to_pallets' => DB::table('ghost_pallet_reports')->whereNotNull('paired_pallet_id')->count(),
];

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;

/**
 * @return list<string>
 */
function ensureImportColumns(bool $dryRun): array
{
    $columns = [];
    $definitions = [
        'pallet_name' => fn (Blueprint $table): mixed => $table->string('pallet_name')->nullable()->after('qr_code'),
        'days_at_customer' => fn (Blueprint $table): mixed => $table->unsignedInteger('days_at_customer')->nullable()->after('last_status_changed_at'),
        'grace_days' => fn (Blueprint $table): mixed => $table->unsignedInteger('grace_days')->nullable()->after('days_at_customer'),
        'overdue_days' => fn (Blueprint $table): mixed => $table->unsignedInteger('overdue_days')->nullable()->after('grace_days'),
        'debt_eur' => fn (Blueprint $table): mixed => $table->decimal('debt_eur', 10, 2)->nullable()->after('overdue_days'),
    ];

    foreach ($definitions as $column => $definition) {
        if (Schema::hasColumn('pallets', $column)) {
            continue;
        }

        $columns[] = $column;

        if ($dryRun) {
            continue;
        }

        Schema::table('pallets', function (Blueprint $table) use ($definition): void {
            $definition($table);
        });
    }

    return $columns;
}

/**
 * @return list<array<string, mixed>>
 */
function parseCsv(string $filePath): array
{
    $handle = fopen($filePath, 'rb');

    if ($handle === false) {
        throw new RuntimeException("Unable to open {$filePath}.");
    }

    $headers = fgetcsv($handle, 0, ',', '"', '');

    if ($headers === false) {
        fclose($handle);
        return [];
    }

    $headers = array_map(static fn (string $header): string => ltrim($header, "\xEF\xBB\xBF"), $headers);
    $rows = [];

    while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
        if ($values === [null] || csvValuesAreBlank($values)) {
            continue;
        }

        $raw = [];
        $mapped = ['raw' => []];

        foreach ($headers as $index => $header) {
            $value = trim((string) ($values[$index] ?? ''));
            $raw[$header] = $value;
            $mapped[canonicalHeader($header)] = $value;
        }

        $mapped['raw'] = $raw;
        $rows[] = $mapped;
    }

    fclose($handle);

    return $rows;
}

function canonicalHeader(string $header): string
{
    return match (normalizeKey($header)) {
        'boknummer' => 'boknummer',
        'beschikbaar ophalen' => 'beschikbaar_ophalen',
        'how to scan' => 'how_to_scan',
        'klant' => 'klant',
        'locatie' => 'locatie',
        'ophalen gemeld op' => 'ophalen_gemeld_op',
        'opmerking' => 'opmerking',
        'qr code' => 'qr_code',
        'terug' => 'terug',
        'type' => 'type',
        'verzonden' => 'verzonden',
        'waar' => 'waar',
        default => normalizeKey($header, '_'),
    };
}

/**
 * @param  list<string|null>  $values
 */
function csvValuesAreBlank(array $values): bool
{
    foreach ($values as $value) {
        if (trim((string) $value) !== '') {
            return false;
        }
    }

    return true;
}

function normalizeKey(string $value, string $separator = ' '): string
{
    $value = ltrim(trim($value), "\xEF\xBB\xBF");
    $value = Str::ascii(mb_strtolower($value, 'UTF-8'));
    $value = preg_replace('/[^a-z0-9]+/', $separator, $value) ?? $value;
    $value = trim($value, $separator);

    return preg_replace('/'.preg_quote($separator, '/').'+/', $separator, $value) ?? $value;
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return list<string>
 */
function duplicateQrCodes(array $rows): array
{
    $seen = [];
    $duplicates = [];

    foreach ($rows as $row) {
        $qrCode = Normalizer::qrCode((string) ($row['boknummer'] ?? ''));

        if ($qrCode === '') {
            continue;
        }

        if (isset($seen[$qrCode])) {
            $duplicates[$qrCode] = $qrCode;
            continue;
        }

        $seen[$qrCode] = true;
    }

    return array_values($duplicates);
}

/**
 * @return array<string, int>
 */
function loadStatusLookup(): array
{
    return DB::table('statuses')->pluck('id', 'slug')->map(fn (mixed $id): int => (int) $id)->all();
}

/**
 * @return array<string, int>
 */
function loadCustomerLookup(): array
{
    $lookup = [];
    $roleId = DB::table('roles')->where('name', 'customer')->value('id');
    $customers = DB::table('users')
        ->leftJoin('customer_details', 'customer_details.user_id', '=', 'users.id')
        ->select('users.id', 'users.name', 'users.role_id', 'customer_details.company_name')
        ->get();

    foreach ($customers as $customer) {
        if ((int) $customer->role_id !== (int) $roleId && $customer->company_name === null) {
            continue;
        }

        foreach ([$customer->company_name, $customer->name] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $lookup[customerKey($candidate)] = (int) $customer->id;
            }
        }
    }

    return $lookup;
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @param  array<string, int>  $statuses
 * @return list<array{row: array<string, mixed>, qr_code: string, status_slug: string}>
 */
function buildImportPreview(array $rows, array $statuses): array
{
    $preview = [];

    foreach ($rows as $row) {
        $qrCode = Normalizer::qrCode((string) ($row['boknummer'] ?? ''));

        if ($qrCode === '') {
            continue;
        }

        $statusSlug = statusSlug($row['waar'] ?? '');

        if (! isset($statuses[$statusSlug])) {
            throw new RuntimeException("Status '{$statusSlug}' was not found for pallet {$qrCode}.");
        }

        $preview[] = [
            'row' => $row,
            'qr_code' => $qrCode,
            'status_slug' => $statusSlug,
        ];
    }

    return $preview;
}

function statusSlug(mixed $waar): string
{
    return match (normalizeKey((string) $waar)) {
        'bij de klant' => 'at_customer',
        'bih nl transport', 'nl bih transport' => 'transport',
        'bowido nl', 'bowido bih' => 'bowido_warehouse',
        'ophalen klant' => 'pending_return',
        'onbekend', 'bih drugo', '' => 'unknown',
        default => 'unknown',
    };
}

/**
 * @param  list<array{status_slug: string}>  $preview
 * @return array<string, int>
 */
function statusCounts(array $preview): array
{
    $counts = [];

    foreach ($preview as $item) {
        $counts[$item['status_slug']] = ($counts[$item['status_slug']] ?? 0) + 1;
    }

    ksort($counts);

    return $counts;
}

/**
 * @param  list<array{row: array<string, mixed>}>  $preview
 * @param  array<string, int>  $customerCache
 */
function countCustomersToCreate(array $preview, array $customerCache): int
{
    $missing = [];

    foreach ($preview as $item) {
        $key = customerKey(customerNameForRow($item['row']));

        if (! isset($customerCache[$key])) {
            $missing[$key] = true;
        }
    }

    return count($missing);
}

/**
 * @return array{price_per_day: string, grace_period_days: int}
 */
function customerBillingDefaults(): array
{
    $status = DB::table('statuses')->where('slug', 'at_customer')->first();

    return [
        'price_per_day' => number_format((float) ($status->price_per_day ?? 2.50), 2, '.', ''),
        'grace_period_days' => (int) ($status->grace_period_days ?? 14),
    ];
}

function customerNameForRow(array $row): string
{
    $customer = nullableString($row['klant'] ?? null);

    return $customer ?? 'Bowido Internal';
}

/**
 * @param  array<string, int>  $customerCache
 * @param  array{price_per_day: string, grace_period_days: int}  $billingDefaults
 */
function getOrCreateCustomerId(
    string $customerName,
    array &$customerCache,
    int $customerRoleId,
    array $billingDefaults,
    array $row,
    int &$createdCustomers,
    Carbon $now,
): int {
    $key = customerKey($customerName);

    if (isset($customerCache[$key])) {
        ensureCustomerDetail($customerCache[$key], $customerName, $billingDefaults, $row, $now);

        return $customerCache[$key];
    }

    $email = uniqueCustomerEmail($customerName);
    $userId = DB::table('users')->insertGetId([
        'role_id' => $customerRoleId,
        'name' => $customerName,
        'email' => $email,
        'phone_number' => null,
        'email_verified_at' => null,
        'password' => Hash::make('password123'),
        'is_active' => true,
        'last_login_at' => null,
        'remember_token' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('customer_details')->insert([
        'user_id' => $userId,
        'company_name' => $customerName,
        'country' => inferCountry($row),
        'kvk' => null,
        'billing_email' => $email,
        'billing_address' => null,
        'delivery_address' => nullableString($row['locatie'] ?? null),
        'tax_number' => null,
        'vat_number' => null,
        'default_price_per_day' => $billingDefaults['price_per_day'],
        'grace_period_days' => $billingDefaults['grace_period_days'],
        'notes' => 'Created during pallet CSV import.',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $customerCache[$key] = $userId;
    $createdCustomers++;

    return $userId;
}

/**
 * @param  array{price_per_day: string, grace_period_days: int}  $billingDefaults
 */
function ensureCustomerDetail(int $userId, string $customerName, array $billingDefaults, array $row, Carbon $now): void
{
    if (DB::table('customer_details')->where('user_id', $userId)->exists()) {
        return;
    }

    DB::table('customer_details')->insert([
        'user_id' => $userId,
        'company_name' => $customerName,
        'country' => inferCountry($row),
        'kvk' => null,
        'billing_email' => DB::table('users')->where('id', $userId)->value('email'),
        'billing_address' => null,
        'delivery_address' => nullableString($row['locatie'] ?? null),
        'tax_number' => null,
        'vat_number' => null,
        'default_price_per_day' => $billingDefaults['price_per_day'],
        'grace_period_days' => $billingDefaults['grace_period_days'],
        'notes' => 'Customer details created during pallet CSV import.',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function customerKey(string $value): string
{
    return normalizeKey($value);
}

function uniqueCustomerEmail(string $customerName): string
{
    $slug = normalizeKey($customerName, '.');
    $slug = $slug !== '' ? Str::limit($slug, 80, '') : 'customer';
    $base = "customer+{$slug}";
    $counter = 0;

    do {
        $suffix = $counter === 0 ? '' : ".{$counter}";
        $email = "{$base}{$suffix}@trackpal.local";
        $counter++;
    } while (DB::table('users')->where('email', $email)->exists());

    return $email;
}

function inferCountry(array $row): string
{
    $where = normalizeKey((string) ($row['waar'] ?? ''));

    return str_contains($where, 'bih') ? 'BiH' : 'NL';
}

function palletType(string $type): string
{
    $type = trim($type);

    return $type !== '' ? $type : 'pallet';
}

function currentLocation(array $row): ?string
{
    return nullableString($row['locatie'] ?? null)
        ?? nullableString($row['waar'] ?? null);
}

function firstFilled(array $row, array $keys): ?string
{
    foreach ($keys as $key) {
        $value = nullableString($row[$key] ?? null);

        if ($value !== null) {
            return $value;
        }
    }

    return null;
}

function parseDate(mixed $value): ?string
{
    $value = nullableString($value);

    if ($value === null) {
        return null;
    }

    try {
        return Carbon::parse($value)->startOfDay()->toDateTimeString();
    } catch (Throwable) {
        return null;
    }
}

function daysAtCustomer(string $statusSlug, ?string $date): ?int
{
    if ($statusSlug !== 'at_customer' || $date === null) {
        return null;
    }

    return (int) Carbon::parse($date)->startOfDay()->diffInDays(now()->startOfDay());
}

function overdueDays(string $returnLabel): ?int
{
    if (! preg_match('/(\d+)\s+dagen\s+over/iu', $returnLabel, $matches)) {
        return null;
    }

    return (int) $matches[1];
}

function nullableString(mixed $value): ?string
{
    $value = trim((string) $value);

    return $value === '' ? null : $value;
}
