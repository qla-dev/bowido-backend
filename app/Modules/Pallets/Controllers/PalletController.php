<?php

namespace App\Modules\Pallets\Controllers;

use App\Modules\Pallets\DTOs\PalletData;
use App\Modules\Pallets\Models\Pallet;
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
            $data['current_location'],
        );

        return $this->successItem($updatedPallet, PalletResource::class, __('Pallet assigned to your possession.'));
    }
}
