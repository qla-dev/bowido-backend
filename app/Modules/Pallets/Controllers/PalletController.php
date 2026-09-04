<?php

namespace App\Modules\Pallets\Controllers;

use App\Modules\Pallets\DTOs\PalletData;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Pallets\Rules\PalletCustomerAssignmentRule;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;
use App\Modules\Pallets\Requests\ListPalletsRequest;
use App\Modules\Pallets\Requests\ClaimCustomerPossessionRequest;
use App\Modules\Pallets\Requests\ScanCustomerPossessionRequest;
use App\Modules\Pallets\Requests\StorePalletRequest;
use App\Modules\Pallets\Requests\UpdatePalletRequest;
use App\Modules\Pallets\Requests\UpdatePalletLocationRequest;
use App\Modules\Pallets\Requests\UpdateClientPalletStatusRequest;
use App\Modules\Pallets\Resources\PalletResource;
use App\Modules\Pallets\Services\PalletService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use App\Modules\Shared\Support\Normalizer;
use Illuminate\Support\Facades\Log;

class PalletController extends ApiController
{
    public function __construct(private readonly PalletService $palletService)
    {
    }

    public function index(ListPalletsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Pallet::class);

        return $this->successCollection(
            $this->palletService->paginate(ListQueryData::fromRequest($request), $request->user()),
            PalletResource::class,
            __('Pallets retrieved successfully.'),
        );
    }

    /**
     * Lightweight source list for QR label exports. Do not use the regular
     * pallet resource here: its related client, status, and location records
     * make an export picker unnecessarily slow for larger fleets.
     */
    public function qrExportList(): JsonResponse
    {
        $actor = request()->user();
        $this->authorize('viewAny', Pallet::class);

        $query = Pallet::query()
            ->select(['id', 'qr_code', 'pallet_name'])
            ->where('is_ghost', false)
            ->whereNotNull('qr_code')
            ->where('qr_code', '!=', '');

        if ($actor?->isCustomer()) {
            $query
                ->where('user_id', $actor->id)
                ->whereHas('currentStatus', fn ($statusQuery) => $statusQuery->whereIn(
                    'slug',
                    PalletCustomerAssignmentRule::ALLOWED_STATUS_SLUGS,
                ));
        }

        return $this->success(
            $query->orderBy('qr_code')->get()->map(fn (Pallet $pallet): array => [
                'id' => $pallet->id,
                'qr_code' => $pallet->qr_code,
                'pallet_name' => $pallet->pallet_name,
            ])->values()->all(),
            __('QR export pallets retrieved successfully.'),
        );
    }

    public function exportQrCodes(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('viewAny', Pallet::class);
        Log::info('QR export requested.', [
            'actor_id' => request()->user()?->id,
            'content_type' => request()->header('Content-Type'),
            'payload_keys' => array_keys(request()->all()),
            'pallet_ids_count' => count((array) request()->input('pallet_ids', [])),
            'formats' => request()->input('formats', []),
        ]);
        $data = request()->validate([
            'pallet_ids' => ['required', 'array', 'min:1', 'max:2000'],
            'pallet_ids.*' => ['integer'],
            'formats' => ['required', 'array', 'min:1', 'max:4'],
            'formats.*' => ['in:svg,png,jpg,pdf'],
        ]);
        $pallets = Pallet::query()->whereIn('id', $data['pallet_ids'])->whereNotNull('qr_code')->where('qr_code', '!=', '')->get(['id', 'pallet_name', 'qr_code']);
        abort_if($pallets->count() !== count(array_unique($data['pallet_ids'])), 404, __('One or more pallets could not be found.'));
        $baseName = 'qr-export';
        $labels = $pallets->map(fn (Pallet $pallet) => $pallet->pallet_name ?: $pallet->qr_code)->values();
        $suffixes = $labels->map(fn (string $label) => preg_match('/(\d+)$/', $label, $matches) ? $matches[1] : null);
        if ($labels->count() === 1) {
            $baseName .= '-'.(Str::slug($labels->first()) ?: 'pallet');
        } elseif ($suffixes->every(fn ($suffix) => $suffix !== null)) {
            $width = $suffixes->map(fn (string $suffix) => strlen($suffix))->max();
            $numbers = $suffixes->map(fn (string $suffix) => (int) $suffix);
            $baseName .= '-'.str_pad((string) $numbers->min(), $width, '0', STR_PAD_LEFT).'-'.str_pad((string) $numbers->max(), $width, '0', STR_PAD_LEFT);
        }
        $path = storage_path('app/tmp/'.$baseName.'-'.Str::uuid().'.zip');
        if (! is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        $zip = new ZipArchive;
        abort_unless($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, __('Unable to prepare the QR export.'));
        $html = '<html><body style="font-family: sans-serif;">';
        foreach ($pallets as $pallet) {
            $qr = new QrCode(data: $pallet->qr_code, size: 500, margin: 12);
            $label = Str::slug($pallet->pallet_name ?: $pallet->qr_code) ?: 'pallet-'.$pallet->id;
            $svg = (new SvgWriter)->write($qr)->getString();
            if (in_array('svg', $data['formats'], true)) $zip->addFromString($label.'.svg', $svg);
            $png = null;
            if (in_array('png', $data['formats'], true) || in_array('jpg', $data['formats'], true) || in_array('pdf', $data['formats'], true)) $png = (new PngWriter)->write($qr)->getString();
            if (in_array('png', $data['formats'], true)) $zip->addFromString($label.'.png', $png);
            if (in_array('jpg', $data['formats'], true) && function_exists('imagecreatefromstring')) { $image = imagecreatefromstring($png); ob_start(); imagejpeg($image, null, 94); $zip->addFromString($label.'.jpg', (string) ob_get_clean()); imagedestroy($image); }
            if (in_array('pdf', $data['formats'], true)) $html .= '<div style="display:inline-block;width:30%;text-align:center;margin:12px;"><img style="width:150px" src="data:image/png;base64,'.base64_encode($png).'"/><br/>'.e($pallet->pallet_name ?: $pallet->qr_code).'</div>';
        }
        if (in_array('pdf', $data['formats'], true)) $zip->addFromString($baseName.'.pdf', Pdf::loadHTML($html.'</body></html>')->setPaper('a4')->output());
        $zip->close();
        Log::info('QR export prepared.', [
            'actor_id' => request()->user()?->id,
            'pallets_count' => $pallets->count(),
            'formats' => $data['formats'],
            'filename' => $baseName.'.zip',
            'bytes' => filesize($path),
        ]);
        return response()->download($path, $baseName.'.zip')->deleteFileAfterSend(true);
    }

    public function exportExcelReport(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('viewAny', Pallet::class);
        Log::info('Excel report export requested.', [
            'actor_id' => request()->user()?->id,
            'content_type' => request()->header('Content-Type'),
            'payload_keys' => array_keys(request()->all()),
            'client_ids_count' => count((array) request()->input('client_ids', [])),
            'language' => request()->input('language'),
        ]);
        $data = request()->validate(['client_ids' => ['nullable', 'array'], 'client_ids.*' => ['integer'], 'language' => ['nullable', 'in:en,nl,bs']]);
        $language = $data['language'] ?? 'en';
        $copy = match ($language) {
            'nl' => ['summary_title' => 'Overzicht bokken per klant', 'summary' => 'Overzicht', 'client' => 'Klant', 'pallets' => 'Aantal bokken', 'overdue' => 'Bokken met schuld', 'debt_total' => 'Totale schuld (EUR)', 'debt' => 'Schuld (EUR)', 'pallet' => 'Bok', 'type' => 'Type', 'status' => 'Status', 'sent' => 'Verzonden', 'days' => 'Dagen bij klant', 'grace' => 'Respijtdagen', 'late' => 'Dagen te laat', 'location' => 'Locatie', 'total' => 'Totaal'],
            'bs' => ['summary_title' => 'Pregled paleta po kupcu', 'summary' => 'Pregled', 'client' => 'Kupac', 'pallets' => 'Broj paleta', 'overdue' => 'Palete s dugom', 'debt_total' => 'Ukupan dug (EUR)', 'debt' => 'Dug (EUR)', 'pallet' => 'Paleta', 'type' => 'Tip', 'status' => 'Status', 'sent' => 'Poslana', 'days' => 'Dana kod kupca', 'grace' => 'Dani tolerancije', 'late' => 'Dana preko', 'location' => 'Lokacija', 'total' => 'Ukupno'],
            default => ['summary_title' => 'Pallet overview by customer', 'summary' => 'Summary', 'client' => 'Customer', 'pallets' => 'Pallet count', 'overdue' => 'Pallets with debt', 'debt_total' => 'Total debt (EUR)', 'debt' => 'Debt (EUR)', 'pallet' => 'Pallet', 'type' => 'Type', 'status' => 'Status', 'sent' => 'Sent', 'days' => 'Days at client', 'grace' => 'Grace', 'late' => 'Days overdue', 'location' => 'Location', 'total' => 'Total'],
        };
        $query = Pallet::query()->with(['user.customerDetail', 'currentStatus'])->whereHas('currentStatus', fn ($q) => $q->where('is_billable', true));
        if (! empty($data['client_ids'])) $query->whereIn('user_id', $data['client_ids']);
        $groups = $query->whereNotNull('user_id')->get()->groupBy(fn (Pallet $pallet) => $pallet->user_id)->map(function ($pallets, $id) use ($copy) {
            $first = $pallets->first(); return ['id' => $id, 'name' => $first->user?->customerDetail?->company_name ?: $first->user?->name ?: $copy['client'].' '.$id, 'pallets' => $pallets];
        })->values();
        $spreadsheet = new Spreadsheet; $spreadsheet->removeSheetByIndex(0);
        $headerStyle = ['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F3F4F6']], 'font' => ['bold' => true], 'alignment' => ['horizontal' => 'center']];
        $titleStyle = ['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E8F5EE']], 'font' => ['bold' => true, 'size' => 14]];
        $totalStyle = ['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'ECFDF5']], 'font' => ['bold' => true]];
        $metrics = function (Pallet $pallet): array { $started = $pallet->customer_timer_started_at ?? $pallet->last_status_changed_at; $days = $started ? $started->copy()->startOfDay()->diffInDays(now()->startOfDay()) : 0; $grace = $pallet->user?->customerDetail?->grace_period_days ?? $pallet->currentStatus?->grace_period_days ?? 0; $overdue = max(0, $days - $grace); return [$started, $days, $grace, $overdue, $overdue * ($pallet->user?->customerDetail?->default_price_per_day ?? $pallet->currentStatus?->price_per_day ?? 0)]; };
        if ($groups->count() > 1) { $sheet = $spreadsheet->createSheet()->setTitle($copy['summary']); $sheet->mergeCells('A1:D1'); $sheet->setCellValue('A1', $copy['summary_title']); $sheet->getStyle('A1:D1')->applyFromArray($titleStyle); $sheet->fromArray([[$copy['client'], $copy['pallets'], $copy['overdue'], $copy['debt_total']]], null, 'A3'); $sheet->getStyle('A3:D3')->applyFromArray($headerStyle); $row = 4; $totals = [0, 0, 0.0]; foreach ($groups as $group) { $total = 0; $late = 0; foreach ($group['pallets'] as $pallet) { [, , , $overdue, $debt] = $metrics($pallet); $total += $debt; $late += $overdue > 0; } $sheet->fromArray([[$group['name'], $group['pallets']->count(), $late, $total]], null, 'A'.$row++); $totals[0] += $group['pallets']->count(); $totals[1] += $late; $totals[2] += $total; } $sheet->fromArray([[$copy['total'], $totals[0], $totals[1], $totals[2]]], null, 'A'.$row); $sheet->getStyle('A'.$row.':D'.$row)->applyFromArray($totalStyle); foreach (range('A', 'D') as $column) $sheet->getColumnDimension($column)->setAutoSize(true); $sheet->getStyle('D4:D'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00); }
        $usedSheetNames = [$copy['summary']];
        foreach ($groups as $group) { $baseSheetName = Str::limit(trim(str_replace(['\\','/','*','?','[',']',':'], ' ', $group['name'])) ?: $copy['client'].'-'.$group['id'], 31, ''); $sheetName = $baseSheetName; $suffix = 2; while (in_array($sheetName, $usedSheetNames, true)) { $sheetName = Str::limit($baseSheetName, 31 - strlen((string) $suffix) - 1, '').'-'.$suffix++; } $usedSheetNames[] = $sheetName; $sheet = $spreadsheet->createSheet()->setTitle($sheetName); $sheet->mergeCells('A1:I1'); $sheet->setCellValue('A1', $copy['client'].': '.$group['name']); $sheet->getStyle('A1:I1')->applyFromArray($titleStyle); $row = 4; $total = 0; $lateCount = 0; foreach ($group['pallets'] as $pallet) { [$started, $days, $grace, $overdue, $debt] = $metrics($pallet); $total += $debt; $lateCount += $overdue > 0; $sheet->fromArray([[$pallet->pallet_name ?: $pallet->qr_code, $pallet->type, $pallet->currentStatus?->name, $started?->format('d.m.Y'), $days, $grace, $overdue, $debt, $pallet->current_location]], null, 'A'.($row + 1)); $row++; } $sheet->fromArray([[$copy['pallets'], $group['pallets']->count(), $copy['overdue'], $lateCount, $copy['debt_total'], $total]], null, 'A2'); $sheet->getStyle('A2:I2')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FAFAFA']], 'font' => ['bold' => true]]); $sheet->fromArray([[$copy['pallet'], $copy['type'], $copy['status'], $copy['sent'], $copy['days'], $copy['grace'], $copy['late'], $copy['debt'], $copy['location']]], null, 'A4'); $sheet->getStyle('A4:I4')->applyFromArray($headerStyle); $totalRow = $row + 1; $sheet->mergeCells('A'.$totalRow.':G'.$totalRow); $sheet->setCellValue('A'.$totalRow, $copy['total']); $sheet->setCellValue('H'.$totalRow, $total); $sheet->getStyle('A'.$totalRow.':I'.$totalRow)->applyFromArray($totalStyle); $sheet->getStyle('H5:H'.$totalRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00); foreach (range('A','I') as $column) $sheet->getColumnDimension($column)->setAutoSize(true); }
        $path = storage_path('app/tmp/customer-report-'.Str::uuid().'.xlsx'); if (! is_dir(dirname($path))) mkdir(dirname($path), 0775, true); (new Xlsx($spreadsheet))->save($path);
        Log::info('Excel report export prepared.', ['actor_id' => request()->user()?->id, 'clients_count' => $groups->count(), 'bytes' => filesize($path)]);
        return response()->download($path, 'trackpal-customer-report.xlsx')->deleteFileAfterSend(true);
    }

    public function store(StorePalletRequest $request): JsonResponse
    {
        $this->authorize('create', Pallet::class);

        $pallet = $this->palletService->create(PalletData::fromArray($request->validated()), $request->user());

        return $this->successItem($pallet, PalletResource::class, __('Pallet created successfully.'), 201);
    }

    public function show(Pallet $pallet): JsonResponse
    {
        $this->authorize('view', $pallet);

        return $this->successItem(
            $this->palletService->find($pallet->id, request()->user()),
            PalletResource::class,
            __('Pallet retrieved successfully.'),
        );
    }

    public function update(UpdatePalletRequest $request, Pallet $pallet): JsonResponse
    {
        $this->authorize('update', $pallet);

        $updatedPallet = $this->palletService->update($pallet, PalletData::fromArray([
            ...$pallet->toArray(),
            ...$request->validated(),
        ]), $request->user());

        return $this->successItem($updatedPallet, PalletResource::class, __('Pallet updated successfully.'));
    }

    public function updateCurrentLocation(UpdatePalletLocationRequest $request, Pallet $pallet): JsonResponse
    {
        $this->authorize('updateClientTracking', $pallet);

        $updatedPallet = $this->palletService->updateCurrentLocation(
            $pallet,
            $request->validated('current_location'),
            $request->user(),
        );

        return $this->successItem($updatedPallet, PalletResource::class, __('Pallet location updated successfully.'));
    }

    public function updateRepairStatus(Pallet $pallet): JsonResponse
    {
        $this->authorize('updateRepairStatus', $pallet);
        $data = request()->validate(['is_for_repair' => ['required', 'boolean']]);
        $updatedPallet = $this->palletService->updateRepairStatus($pallet, (bool) $data['is_for_repair'], request()->user());

        return $this->successItem($updatedPallet, PalletResource::class, __('Pallet repair status updated successfully.'));
    }

    public function updateClientStatus(UpdateClientPalletStatusRequest $request, Pallet $pallet): JsonResponse
    {
        $this->authorize('updateClientTracking', $pallet);

        $data = $request->validated();
        $updatedPallet = $this->palletService->updateClientStatus(
            $pallet,
            (int) $data['current_status_id'],
            $request->user(),
            array_key_exists('current_location', $data) ? $data['current_location'] : null,
        );

        return $this->successItem($updatedPallet, PalletResource::class, __('Pallet status updated successfully.'));
    }

    public function destroy(Pallet $pallet): JsonResponse
    {
        $this->authorize('delete', $pallet);

        $this->palletService->delete($pallet->id, request()->user());

        return $this->success(null, __('Pallet deleted successfully.'));
    }

    public function scanCustomerPossession(ScanCustomerPossessionRequest $request): JsonResponse
    {
        $this->authorize('scanCustomerPossession', Pallet::class);
        $qrCode = Normalizer::qrCode($request->validated('qr_code'));
        $pallet = Pallet::query()
            ->with(['user.customerDetail', 'currentStatus', 'deliveryLocation'])
            ->where('qr_code', $qrCode)
            ->first();

        Log::info('Customer QR scan lookup completed.', [
            'actor_id' => $request->user()->id,
            'qr_code_hash' => hash('sha256', $qrCode),
            'qr_code_length' => mb_strlen($qrCode),
            'matched_pallet_id' => $pallet?->id,
        ]);

        if ($pallet === null) {
            Log::warning('Customer QR scan lookup did not match a pallet.', [
                'actor_id' => $request->user()->id,
                'qr_code_hash' => hash('sha256', $qrCode),
                'pallets_with_qr_code' => Pallet::query()->whereNotNull('qr_code')->where('qr_code', '!=', '')->count(),
            ]);

            abort(404, __('The scanned QR code is not linked to a pallet.'));
        }

        return $this->successItem($pallet, PalletResource::class, __('Pallet scanned successfully.'));
    }

    /**
     * Resolves a mobile QR scan directly against the database. This prevents
     * scanning from depending on a fully loaded browser-side pallet cache.
     */
    public function scanLookup(): JsonResponse
    {
        $request = request();
        $this->authorize('viewAny', Pallet::class);

        $data = $request->validate([
            'qr_code' => ['required', 'string', 'min:3', 'max:255'],
            'scanned_candidates' => ['required', 'array', 'min:1', 'max:10'],
            'scanned_candidates.*' => ['string', 'min:3', 'max:255'],
        ]);
        $rawQrCode = $data['qr_code'];
        $candidates = collect([$rawQrCode, Normalizer::qrCode($rawQrCode), ...$data['scanned_candidates']])
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->unique()
            ->values();
        $normalizedCandidates = $candidates->map(fn (string $value): string => mb_strtolower($value))->all();
        $placeholders = implode(', ', array_fill(0, count($normalizedCandidates), '?'));
        $bindings = [...$normalizedCandidates, ...$normalizedCandidates, ...$normalizedCandidates];
        $pallet = Pallet::query()
            ->with(['user.customerDetail', 'currentStatus', 'deliveryLocation'])
            ->whereRaw(
                "LOWER(qr_code) IN ({$placeholders}) OR LOWER(pallet_name) IN ({$placeholders}) OR LOWER(reference_code) IN ({$placeholders})",
                $bindings,
            )
            ->first();

        Log::info('Mobile QR scan lookup completed.', [
            'actor_id' => $request->user()->id,
            'actor_role' => $request->user()->role_name ?? null,
            'raw_qr_code' => $rawQrCode,
            'raw_qr_code_hash' => hash('sha256', $rawQrCode),
            'scanned_candidates' => $candidates->all(),
            'matched_pallet_id' => $pallet?->id,
        ]);

        if ($pallet === null) {
            Log::warning('Mobile QR scan lookup did not match a pallet.', [
                'actor_id' => $request->user()->id,
                'raw_qr_code' => $rawQrCode,
                'scanned_candidates' => $candidates->all(),
            ]);

            abort(404, __('The scanned QR code is not linked to a pallet.'));
        }

        $this->authorize('view', $pallet);

        return $this->successItem($pallet, PalletResource::class, __('Pallet scanned successfully.'));
    }

    /**
     * Records an unmatched client-side QR scan. Mobile scans are matched against
     * the browser cache first, so Laravel otherwise never sees a failed scan
     * when that cache is empty or stale.
     */
    public function scanDiagnostics(): JsonResponse
    {
        $request = request();
        $this->authorize('viewAny', Pallet::class);

        $data = $request->validate([
            'raw_qr_code' => ['required', 'string', 'min:3', 'max:255'],
            'scanned_candidates' => ['required', 'array', 'min:1', 'max:10'],
            'scanned_candidates.*' => ['string', 'min:3', 'max:255'],
            'loaded_pallet_count' => ['required', 'integer', 'min:0'],
        ]);
        $rawQrCode = $data['raw_qr_code'];
        $candidates = collect([$rawQrCode, Normalizer::qrCode($rawQrCode), ...$data['scanned_candidates']])
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->unique()
            ->values();
        $normalizedCandidates = $candidates->map(fn (string $value): string => mb_strtolower($value))->all();
        $placeholders = implode(', ', array_fill(0, count($normalizedCandidates), '?'));
        $databaseMatches = Pallet::query()
            ->select(['id', 'qr_code', 'pallet_name', 'reference_code', 'user_id', 'current_status_id', 'is_active', 'is_ghost'])
            ->whereRaw("LOWER(qr_code) IN ({$placeholders})", $normalizedCandidates)
            ->get();

        Log::warning('Mobile QR scan was not recognized by the loaded pallet cache.', [
            'actor_id' => $request->user()->id,
            'actor_role' => $request->user()->role_name ?? null,
            'raw_qr_code' => $rawQrCode,
            'raw_qr_code_hash' => hash('sha256', $rawQrCode),
            'normalized_qr_code' => Normalizer::qrCode($rawQrCode),
            'scanned_candidates' => $candidates->all(),
            'loaded_pallet_count' => $data['loaded_pallet_count'],
            'database_match_count' => $databaseMatches->count(),
            'database_matches' => $databaseMatches->map(fn (Pallet $pallet): array => [
                'id' => $pallet->id,
                'qr_code' => $pallet->qr_code,
                'pallet_name' => $pallet->pallet_name,
                'reference_code' => $pallet->reference_code,
                'user_id' => $pallet->user_id,
                'current_status_id' => $pallet->current_status_id,
                'is_active' => $pallet->is_active,
                'is_ghost' => $pallet->is_ghost,
            ])->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->success(null, __('QR scan diagnostic logged.'));
    }

    public function claimCustomerPossession(
        ClaimCustomerPossessionRequest $request,
        Pallet $pallet,
    ): JsonResponse {
        $this->authorize('claimCustomerPossession', $pallet);
        $data = $request->validated();
        $updatedPallet = $this->palletService->claimCustomerPossession(
            $pallet,
            $request->user(),
            (int) $data['current_status_id'],
            $data['current_location'] ?? null,
        );

        return $this->successItem($updatedPallet, PalletResource::class, __('Pallet assigned to your possession.'));
    }
}
