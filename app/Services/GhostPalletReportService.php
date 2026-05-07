<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\GhostPalletReport;
use App\Models\Pallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class GhostPalletReportService
{
    public function __construct(private readonly AuditTrailService $auditTrailService)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): GhostPalletReport
    {
        $userId = $actor->isCustomer() ? $actor->id : ((int) ($attributes['user_id'] ?? $actor->id));
        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user is not active.'],
            ]);
        }

        /** @var GhostPalletReport $ghostPalletReport */
        $ghostPalletReport = GhostPalletReport::query()->create([
            'user_id' => $userId,
            'status' => GhostPalletReport::STATUS_OPEN,
            'quantity' => (int) $attributes['quantity'],
            'location' => $this->normalizeNullableText($attributes['location'] ?? null),
            'description' => $this->normalizeNullableText($attributes['description'] ?? null),
            'notes' => $this->normalizeNullableText($attributes['notes'] ?? null),
            'reported_at' => now(),
            'metadata' => $attributes['metadata'] ?? null,
        ]);

        return $ghostPalletReport->load(['user.role', 'pairedPallet.currentStatus']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(GhostPalletReport $ghostPalletReport, array $attributes, User $actor): GhostPalletReport
    {
        return DB::transaction(function () use ($ghostPalletReport, $attributes, $actor): GhostPalletReport {
            $lockedGhostPalletReport = GhostPalletReport::query()->lockForUpdate()->findOrFail($ghostPalletReport->id);
            $wasUnpaired = $lockedGhostPalletReport->paired_pallet_id === null;
            $userId = (int) ($attributes['user_id'] ?? $lockedGhostPalletReport->user_id);
            /** @var User $user */
            $user = User::query()->findOrFail($userId);

            if (! $user->is_active) {
                throw ValidationException::withMessages([
                    'user_id' => ['The selected user is not active.'],
                ]);
            }

            $updateAttributes = [
                'user_id' => $userId,
                'quantity' => (int) ($attributes['quantity'] ?? $lockedGhostPalletReport->quantity),
                'location' => array_key_exists('location', $attributes)
                    ? $this->normalizeNullableText($attributes['location'])
                    : $lockedGhostPalletReport->location,
                'description' => array_key_exists('description', $attributes)
                    ? $this->normalizeNullableText($attributes['description'])
                    : $lockedGhostPalletReport->description,
                'notes' => array_key_exists('notes', $attributes)
                    ? $this->normalizeNullableText($attributes['notes'])
                    : $lockedGhostPalletReport->notes,
                'metadata' => $attributes['metadata'] ?? $lockedGhostPalletReport->metadata,
            ];

            if (
                $lockedGhostPalletReport->paired_pallet_id !== null
                && ($attributes['paired_pallet_id'] ?? null) !== null
                && $lockedGhostPalletReport->paired_pallet_id !== (int) $attributes['paired_pallet_id']
            ) {
                throw ValidationException::withMessages([
                    'paired_pallet_id' => ['Paired ghost pallet reports cannot be re-assigned to a different pallet.'],
                ]);
            }

            if (($attributes['paired_pallet_id'] ?? null) !== null && $lockedGhostPalletReport->paired_pallet_id === null) {
                $pallet = Pallet::query()->findOrFail((int) $attributes['paired_pallet_id']);
                $updateAttributes['paired_pallet_id'] = $pallet->id;
                $updateAttributes['paired_at'] = now();
                $updateAttributes['status'] = GhostPalletReport::STATUS_PAIRED;
            } elseif (($attributes['status'] ?? null) !== null) {
                $updateAttributes['status'] = $attributes['status'];
            }

            $lockedGhostPalletReport->fill($updateAttributes);
            $lockedGhostPalletReport->save();

            if ($lockedGhostPalletReport->paired_pallet_id !== null && $wasUnpaired) {
                $this->auditTrailService->record(
                    palletId: $lockedGhostPalletReport->paired_pallet_id,
                    madeByUserId: $actor->id,
                    eventType: AuditLog::EVENT_GHOST_PAIRED,
                    note: $lockedGhostPalletReport->notes,
                    context: [
                        'ghost_pallet_report_id' => $lockedGhostPalletReport->id,
                        'quantity' => $lockedGhostPalletReport->quantity,
                    ],
                );
            }

            return $lockedGhostPalletReport->fresh(['user.role', 'pairedPallet.currentStatus']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function reportPallets(array $attributes, User $actor): GhostPalletReport
    {
        return $this->create([
            'user_id' => $attributes['customer_id'] ?? $attributes['user_id'] ?? null,
            'quantity' => $attributes['quantity'],
            'location' => $attributes['location'] ?? null,
            'description' => $attributes['description'] ?? $attributes['note'] ?? null,
            'notes' => $attributes['note'] ?? null,
            'metadata' => [
                'reported_from_workflow' => true,
            ],
        ], $actor);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function pairReport(GhostPalletReport $ghostPalletReport, array $attributes, User $actor): GhostPalletReport
    {
        return DB::transaction(function () use ($ghostPalletReport, $attributes, $actor): GhostPalletReport {
            $lockedGhostReport = GhostPalletReport::query()->lockForUpdate()->findOrFail($ghostPalletReport->id);
            $pallet = Pallet::query()->findOrFail((int) $attributes['pallet_id']);
            $quantityToPair = (int) ($attributes['quantity_to_pair'] ?? $lockedGhostReport->quantity);
            $pairedQuantity = (int) data_get($lockedGhostReport->metadata, 'paired_quantity', 0);
            $remainingQuantity = max(0, $lockedGhostReport->quantity - $pairedQuantity);

            if ($remainingQuantity === 0) {
                throw new BadRequestHttpException('This ghost pallet report is already fully paired.');
            }

            if ($quantityToPair < 1 || $quantityToPair > $remainingQuantity) {
                throw ValidationException::withMessages([
                    'quantity_to_pair' => ['The quantity to pair must not exceed the remaining unpaired quantity.'],
                ]);
            }

            if (($attributes['qr_code'] ?? null) !== null) {
                $normalizedQrCode = Pallet::normalizeQrCode((string) $attributes['qr_code']);

                if ($pallet->qr_code !== $normalizedQrCode) {
                    throw ValidationException::withMessages([
                        'qr_code' => ['The supplied QR code does not match the selected pallet.'],
                    ]);
                }
            }

            $pairings = collect(data_get($lockedGhostReport->metadata, 'pairings', []))
                ->push([
                    'pallet_id' => $pallet->id,
                    'qr_code' => $pallet->qr_code,
                    'quantity' => $quantityToPair,
                    'paired_by_user_id' => $actor->id,
                    'paired_at' => now()->toIso8601String(),
                    'note' => $attributes['note'] ?? null,
                ])
                ->values()
                ->all();
            $newPairedQuantity = $pairedQuantity + $quantityToPair;
            $fullyPaired = $newPairedQuantity >= $lockedGhostReport->quantity;

            $lockedGhostReport->fill([
                'paired_pallet_id' => $pallet->id,
                'status' => $fullyPaired ? GhostPalletReport::STATUS_PAIRED : GhostPalletReport::STATUS_OPEN,
                'notes' => $attributes['note'] ?? $lockedGhostReport->notes,
                'paired_at' => $fullyPaired ? now() : null,
                'metadata' => [
                    ...($lockedGhostReport->metadata ?? []),
                    'paired_quantity' => $newPairedQuantity,
                    'pairings' => $pairings,
                ],
            ]);
            $lockedGhostReport->save();

            $this->auditTrailService->record(
                palletId: $pallet->id,
                madeByUserId: $actor->id,
                eventType: AuditLog::EVENT_GHOST_PAIRED,
                note: $attributes['note'] ?? null,
                context: [
                    'ghost_pallet_report_id' => $lockedGhostReport->id,
                    'quantity_to_pair' => $quantityToPair,
                    'remaining_quantity' => max(0, $lockedGhostReport->quantity - $newPairedQuantity),
                ],
            );

            return $lockedGhostReport->fresh(['user.role', 'pairedPallet.currentStatus']);
        });
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}