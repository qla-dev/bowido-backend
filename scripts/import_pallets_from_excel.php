<?php

declare(strict_types=1);

use App\Modules\Statuses\Models\Status;
use App\Modules\Shared\Support\Normalizer;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const SS_NS = 'urn:schemas-microsoft-com:office:spreadsheet';

$defaultPath = 'C:\\Users\\DT User\\Downloads\\palete-po-kupcu-all-2026-07-02.xls';
$args = array_values(array_slice($argv, 1));
$dryRun = in_array('--dry-run', $args, true);
$filePath = collect($args)->first(fn (string $arg): bool => ! str_starts_with($arg, '--')) ?? $defaultPath;

if (! is_string($filePath) || ! is_file($filePath)) {
    fwrite(STDERR, "Excel file not found: {$filePath}\n");
    exit(1);
}

$columnAdditions = ensureImportColumns($dryRun);
$parsedRows = parseWorkbook($filePath);

if ($parsedRows === []) {
    fwrite(STDERR, "No pallet detail rows were found in {$filePath}.\n");
    exit(1);
}

$customers = loadCustomerLookup();
$statuses = loadStatusLookup();
$results = [
    'inserted' => 0,
    'updated' => 0,
    'skipped' => 0,
    'columns_added' => $columnAdditions,
    'rows' => [],
];

$operation = function () use ($parsedRows, $customers, $statuses, &$results, $filePath): void {
    foreach ($parsedRows as $row) {
        $customerId = resolveCustomerId((string) $row['customer_name'], $customers);

        if ($customerId === null) {
            throw new RuntimeException("Customer not found for '{$row['customer_name']}'.");
        }

        $statusId = resolveStatusId((string) $row['status'], $statuses);

        if ($statusId === null) {
            throw new RuntimeException("Status not found for '{$row['status']}' on pallet '{$row['pallet_name']}'.");
        }

        $palletName = Normalizer::qrCode((string) $row['pallet_name']);
        $rawType = trim((string) $row['type']);
        $type = translateTypeValue($rawType);
        $qrCode = $palletName;
        $now = now();

        $existing = findExistingPallet($palletName, $qrCode);

        if ($existing !== null) {
            $conflict = DB::table('pallets')
                ->where('qr_code', $qrCode)
                ->where('id', '<>', $existing->id)
                ->first();

            if ($conflict !== null) {
                throw new RuntimeException("QR code '{$qrCode}' already belongs to pallet id {$conflict->id}.");
            }
        }

        $metadata = [];

        if ($existing !== null && is_string($existing->metadata) && $existing->metadata !== '') {
            $decoded = json_decode($existing->metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        $metadata['source'] = 'excel_import';
        $metadata['source_file'] = basename($filePath);
        $metadata['source_customer_sheet'] = $row['sheet_name'];
        $metadata['original_excel'] = [
            'pallet_name' => $palletName,
            'type' => $rawType,
            'status' => $row['status'],
            'sent_date' => $row['last_status_changed_at'],
            'days_at_customer' => $row['days_at_customer'],
            'grace_days' => $row['grace_days'],
            'overdue_days' => $row['overdue_days'],
            'debt_eur' => $row['debt_eur'],
            'current_location' => $row['current_location'],
        ];

        $attributes = [
            'user_id' => $customerId,
            'current_status_id' => $statusId,
            'type' => $type,
            'asset_type' => 'pallet',
            'qr_code' => $qrCode,
            'pallet_name' => $palletName,
            'reference_code' => $existing?->reference_code ?: $palletName,
            'current_location' => nullableString($row['current_location'] ?? null),
            'last_status_changed_at' => parseDate($row['last_status_changed_at'] ?? null),
            'days_at_customer' => nullableInteger($row['days_at_customer'] ?? null),
            'grace_days' => nullableInteger($row['grace_days'] ?? null),
            'overdue_days' => nullableInteger($row['overdue_days'] ?? null),
            'debt_eur' => nullableDecimal($row['debt_eur'] ?? null),
            'is_active' => true,
            'is_ghost' => false,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ];

        if ($existing !== null) {
            DB::table('pallets')->where('id', $existing->id)->update($attributes);
            $results['updated']++;
            $action = 'updated';
        } else {
            $attributes['created_at'] = $now;
            DB::table('pallets')->insert($attributes);
            $results['inserted']++;
            $action = 'inserted';
        }

        $results['rows'][] = [
            'action' => $action,
            'customer' => $row['customer_name'],
            'pallet_name' => $palletName,
            'type' => $type,
            'qr_code' => $qrCode,
        ];
    }
};

if ($dryRun) {
    echo "Dry run only. No database rows will be changed.\n";
    foreach ($parsedRows as $row) {
        $palletName = Normalizer::qrCode((string) $row['pallet_name']);
        echo "- would import {$palletName} for {$row['customer_name']} with QR {$palletName}\n";
    }
    exit(0);
}

DB::transaction($operation);

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

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
function parseWorkbook(string $filePath): array
{
    $document = new DOMDocument();
    $document->load($filePath);

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('ss', SS_NS);

    $rows = [];

    foreach ($xpath->query('//ss:Worksheet') as $worksheet) {
        if (! $worksheet instanceof DOMElement) {
            continue;
        }

        $sheetName = $worksheet->getAttributeNS(SS_NS, 'Name');
        $sheetRows = readWorksheetRows($xpath, $worksheet);
        $headerIndex = findHeaderRowIndex($sheetRows);

        if ($headerIndex === null) {
            continue;
        }

        $customerName = parseCustomerName($sheetRows, $sheetName);
        $headerMap = buildHeaderMap($sheetRows[$headerIndex]);

        for ($index = $headerIndex + 1; $index < count($sheetRows); $index++) {
            $row = $sheetRows[$index];
            $firstCell = trim((string) ($row[0] ?? ''));

            if ($firstCell === '' || in_array(normalizeHeader($firstCell), ['ukupno', 'total', 'totaal'], true)) {
                continue;
            }

            $mapped = [
                'customer_name' => $customerName,
                'sheet_name' => $sheetName,
            ];

            foreach ($headerMap as $cellIndex => $column) {
                $mapped[$column] = trim((string) ($row[$cellIndex] ?? ''));
            }

            if (($mapped['pallet_name'] ?? '') === '') {
                continue;
            }

            $rows[] = $mapped;
        }
    }

    return $rows;
}

/**
 * @return list<list<string>>
 */
function readWorksheetRows(DOMXPath $xpath, DOMElement $worksheet): array
{
    $rows = [];

    foreach ($xpath->query('./ss:Table/ss:Row', $worksheet) as $rowNode) {
        if (! $rowNode instanceof DOMElement) {
            continue;
        }

        $row = [];
        $columnIndex = 0;

        foreach ($xpath->query('./ss:Cell', $rowNode) as $cellNode) {
            if (! $cellNode instanceof DOMElement) {
                continue;
            }

            $explicitIndex = $cellNode->getAttributeNS(SS_NS, 'Index');

            if ($explicitIndex !== '') {
                $columnIndex = max(0, (int) $explicitIndex - 1);
            }

            $data = $xpath->query('./ss:Data', $cellNode)->item(0);
            $row[$columnIndex] = $data?->textContent ?? '';
            $columnIndex++;
        }

        if ($row === []) {
            $rows[] = [];
            continue;
        }

        ksort($row);
        $max = max(array_keys($row));
        $rows[] = array_map(fn (int $index): string => (string) ($row[$index] ?? ''), range(0, $max));
    }

    return $rows;
}

/**
 * @param list<list<string>> $rows
 */
function findHeaderRowIndex(array $rows): ?int
{
    foreach ($rows as $index => $row) {
        foreach ($row as $cell) {
            if (canonicalColumn($cell) === 'pallet_name') {
                return $index;
            }
        }
    }

    return null;
}

/**
 * @param list<list<string>> $rows
 */
function parseCustomerName(array $rows, string $fallback): string
{
    $firstCell = trim((string) ($rows[0][0] ?? ''));

    foreach (['Kupac:', 'Customer:', 'Klant:'] as $prefix) {
        if (str_starts_with($firstCell, $prefix)) {
            return trim(substr($firstCell, strlen($prefix)));
        }
    }

    return $fallback;
}

/**
 * @param list<string> $headerRow
 * @return array<int, string>
 */
function buildHeaderMap(array $headerRow): array
{
    $map = [];

    foreach ($headerRow as $index => $header) {
        $column = canonicalColumn($header);

        if ($column !== null) {
            $map[$index] = $column;
        }
    }

    return $map;
}

function canonicalColumn(string $header): ?string
{
    return match (normalizeHeader($header)) {
        'paleta', 'pallet', 'pallet name', 'palletnaam' => 'pallet_name',
        'tip', 'type', 'soort' => 'type',
        'status' => 'status',
        'poslana', 'poslano', 'sent', 'verzonden' => 'last_status_changed_at',
        'dana kod kupca', 'days at client', 'days at customer', 'dagen bij klant' => 'days_at_customer',
        'grace', 'grace days', 'graceperiode', 'period odgode' => 'grace_days',
        'dana preko', 'days overdue', 'overdue days', 'dagen over' => 'overdue_days',
        'dug eur', 'debt eur', 'schuld eur' => 'debt_eur',
        'lokacija', 'location', 'locatie' => 'current_location',
        default => null,
    };
}

function normalizeHeader(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = strtr($value, [
        'č' => 'c',
        'ć' => 'c',
        'š' => 's',
        'đ' => 'd',
        'ž' => 'z',
        'ë' => 'e',
        'é' => 'e',
        'è' => 'e',
        'ï' => 'i',
        'á' => 'a',
        'ö' => 'o',
        'ü' => 'u',
    ]);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

/**
 * @return array<string, int>
 */
function loadCustomerLookup(): array
{
    $lookup = [];

    $customers = DB::table('users')
        ->leftJoin('customer_details', 'customer_details.user_id', '=', 'users.id')
        ->select('users.id', 'users.name', 'customer_details.company_name')
        ->get();

    foreach ($customers as $customer) {
        foreach ([$customer->company_name, $customer->name] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $lookup[normalizeLookup($candidate)] = (int) $customer->id;
            }
        }
    }

    return $lookup;
}

/**
 * @return array<string, int>
 */
function loadStatusLookup(): array
{
    $lookup = [
        normalizeLookup('Kod klijenta') => null,
        normalizeLookup('Bij de klant') => null,
        normalizeLookup('At Customer') => null,
        normalizeLookup('Transport') => null,
        normalizeLookup('U transportu') => null,
        normalizeLookup('Pending Return') => null,
        normalizeLookup('Paleta vracena') => null,
        normalizeLookup('Service') => null,
        normalizeLookup('Servis') => null,
        normalizeLookup('Unknown') => null,
        normalizeLookup('Nepoznato') => null,
    ];

    $statuses = Status::query()->select('id', 'name', 'slug')->get();
    $slugIds = [];

    foreach ($statuses as $status) {
        $lookup[normalizeLookup((string) $status->name)] = (int) $status->id;
        $lookup[normalizeLookup((string) $status->slug)] = (int) $status->id;
        $slugIds[(string) $status->slug] = (int) $status->id;
    }

    $aliases = [
        'Kod klijenta' => 'at_customer',
        'Bij de klant' => 'at_customer',
        'At Customer' => 'at_customer',
        'Transport' => 'transport',
        'U transportu' => 'transport',
        'Pending Return' => 'pending_return',
        'Paleta vracena' => 'pending_return',
        'Service' => 'service',
        'Servis' => 'service',
        'Unknown' => 'unknown',
        'Nepoznato' => 'unknown',
    ];

    foreach ($aliases as $alias => $slug) {
        if (isset($slugIds[$slug])) {
            $lookup[normalizeLookup($alias)] = $slugIds[$slug];
        }
    }

    return array_filter($lookup, static fn (?int $id): bool => $id !== null);
}

function normalizeLookup(string $value): string
{
    return normalizeHeader($value);
}

/**
 * @param array<string, int> $customers
 */
function resolveCustomerId(string $customerName, array $customers): ?int
{
    return $customers[normalizeLookup($customerName)] ?? null;
}

/**
 * @param array<string, int> $statuses
 */
function resolveStatusId(string $statusName, array $statuses): ?int
{
    return $statuses[normalizeLookup($statusName)] ?? null;
}

function translateTypeValue(string $value): string
{
    return match (normalizeLookup($value)) {
        'grijs' => 'Gray',
        default => $value,
    };
}

function findExistingPallet(string $palletName, string $qrCode): ?object
{
    $query = DB::table('pallets');

    $query->where(function ($builder) use ($palletName, $qrCode): void {
        $builder->where('qr_code', $qrCode)
            ->orWhere('qr_code', $palletName);

        if (Schema::hasColumn('pallets', 'pallet_name')) {
            $builder->orWhere('pallet_name', $palletName);
        }
    });

    return $query->first();
}

function nullableString(mixed $value): ?string
{
    $value = trim((string) $value);

    return $value === '' ? null : $value;
}

function parseDate(mixed $value): ?string
{
    $value = nullableString($value);

    if ($value === null) {
        return null;
    }

    return Carbon::parse($value)->startOfDay()->toDateTimeString();
}

function nullableInteger(mixed $value): ?int
{
    $value = nullableString($value);

    return $value === null ? null : (int) $value;
}

function nullableDecimal(mixed $value): ?string
{
    $value = nullableString($value);

    if ($value === null) {
        return null;
    }

    return number_format((float) str_replace(',', '.', $value), 2, '.', '');
}
