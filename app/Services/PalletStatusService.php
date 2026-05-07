<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Pallet;
use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PalletStatusService
{
    public function __construct(private readonly AuditTrailService $auditTrailService)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function changeStatus(Pallet $pallet, array $attributes, User $actor): Pallet
    {
        return DB::transaction(fn (): Pallet => $this->applyStatusChange($pallet, $attributes, $actor));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, Pallet>
     */
    public function bulkChangeStatus(array $qrCodes, array $attributes, User $actor): Collection
    {
        return DB::transaction(function () use ($qrCodes, $attributes, $actor): Collection {
            $normalizedCodes = collect($qrCodes)
                ->map(fn (string $qrCode): string => Pallet::normalizeQrCode($qrCode))
                ->values();

            $pallets = Pallet::query()
                ->with(['user.role', 'user.customerDetail', 'currentStatus'])
                ->whereIn('qr_code', $normalizedCodes)
                ->lockForUpdate()
                ->get()
                ->keyBy('qr_code');

            $missingCodes = $normalizedCodes
                ->reject(fn (string $qrCode): bool => $pallets->has($qrCode))
                ->values();

            if ($missingCodes->isNotEmpty()) {
                throw new NotFoundHttpException('Pallets not found for QR codes: '.$missingCodes->implode(', ').'.');
            }

            return $normalizedCodes
                ->map(fn (string $qrCode): Pallet => $this->applyStatusChange(
                    pallet: $pallets->get($qrCode),
                    attributes: $attributes,
                    actor: $actor,
                    options: ['action' => 'bulk_change_status'],
                ))
                ->values();
        });
    }

    public function markReadyForReturn(Pallet $pallet, ?string $note, User $actor): Pallet
    {
        return DB::transaction(function () use ($pallet, $note, $actor): Pallet {
            $status = $this->statusBySlug('pending_return');

            return $this->applyStatusChange(
                pallet: $pallet,
                attributes: [
                    'status_id' => $status->id,
                    'note' => $note,
                ],
                actor: $actor,
                options: ['action' => 'mark_ready_for_return'],
            );
        });
    }

    public function markUnknown(Pallet $pallet, ?string $reason, User $actor): Pallet
    {
        return DB::transaction(function () use ($pallet, $reason, $actor): Pallet {
            $status = $this->statusBySlug('unknown');

            return $this->applyStatusChange(
                pallet: $pallet,
                attributes: [
                    'status_id' => $status->id,
                    'reason' => $reason,
                ],
                actor: $actor,
                options: ['action' => 'mark_unknown'],
            );
        });
    }

    public function moveToService(Pallet $pallet, ?string $location, ?string $note, User $actor): Pallet
    {
        return DB::transaction(function () use ($pallet, $location, $note, $actor): Pallet {
            $status = $this->statusBySlug('service');

            return $this->applyStatusChange(
                pallet: $pallet,
                attributes: [
                    'status_id' => $status->id,
                    'location' => $location,
                    'note' => $note,
                ],
                actor: $actor,
                options: [
                    'allow_service_entry' => true,
                    'action' => 'report_service',
                ],
            );
        });
    }

    public function transitionFromService(Pallet $pallet, int $statusId, ?string $location, ?string $note, User $actor): Pallet
    {
        return DB::transaction(function () use ($pallet, $statusId, $location, $note, $actor): Pallet {
            return $this->applyStatusChange(
                pallet: $pallet,
                attributes: [
                    'status_id' => $statusId,
                    'location' => $location,
                    'note' => $note,
                ],
                actor: $actor,
                options: ['action' => 'resolve_service'],
            );
        });
    }

    /**
     * @return array<int, string>
     */
    public function allowedNextActions(Pallet $pallet, User $actor): array
    {
        $pallet->loadMissing('currentStatus');

        $actions = [];

        if (! $actor->isCustomer()) {
            $actions[] = 'change_status';
            $actions[] = 'report_service';
        }

        if (
            $pallet->currentStatus?->slug === 'at_customer'
            && (! $actor->isCustomer() || $pallet->user_id === $actor->id)
        ) {
            $actions[] = 'mark_ready_for_return';
        }

        if ($actor->isAdmin()) {
            $actions[] = 'mark_unknown';
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    private function applyStatusChange(Pallet $pallet, array $attributes, User $actor, array $options = []): Pallet
    {
        $lockedPallet = Pallet::query()
            ->with(['currentStatus', 'user.customerDetail'])
            ->lockForUpdate()
            ->findOrFail($pallet->id);

        /** @var Status $newStatus */
        $newStatus = Status::query()->findOrFail((int) $attributes['status_id']);

        if (! $newStatus->is_active) {
            throw ValidationException::withMessages([
                'status_id' => ['The selected status is not active.'],
            ]);
        }

        $targetCustomerId = $lockedPallet->user_id;

        if (($attributes['customer_id'] ?? null) !== null) {
            /** @var User $customer */
            $customer = User::query()->findOrFail((int) $attributes['customer_id']);

            if (! $customer->is_active) {
                throw ValidationException::withMessages([
                    'customer_id' => ['The selected customer is not active.'],
                ]);
            }

            $targetCustomerId = $customer->id;
        }

        $newLocation = array_key_exists('location', $attributes)
            ? $this->normalizeNullableText($attributes['location'])
            : $lockedPallet->current_location;
        $newQrCode = array_key_exists('qr_code', $attributes) && $attributes['qr_code'] !== null
            ? Pallet::normalizeQrCode((string) $attributes['qr_code'])
            : null;
        $originalStatusId = $lockedPallet->current_status_id;
        $originalCustomerId = $lockedPallet->user_id;
        $originalLocation = $lockedPallet->current_location;
        $originalQrCode = $lockedPallet->qr_code;

        $this->assertTransitionAllowed(
            pallet: $lockedPallet,
            newStatus: $newStatus,
            actor: $actor,
            targetCustomerId: $targetCustomerId,
            options: $options,
        );

        $statusChanged = $originalStatusId !== $newStatus->id;
        $customerChanged = $originalCustomerId !== $targetCustomerId;
        $locationChanged = (string) ($originalLocation ?? '') !== (string) ($newLocation ?? '');
        $qrChanged = $newQrCode !== null && $originalQrCode !== $newQrCode;

        if (! $statusChanged && ! $customerChanged && ! $locationChanged && ! $qrChanged) {
            throw new BadRequestHttpException('The pallet is already in the requested state.');
        }

        $lockedPallet->fill([
            'current_status_id' => $newStatus->id,
            'user_id' => $targetCustomerId,
            'current_location' => $newLocation,
        ]);

        if ($statusChanged) {
            $lockedPallet->last_status_changed_at = now();
        }

        if ($qrChanged) {
            $lockedPallet->qr_code = $newQrCode;
        }

        $lockedPallet->save();

        $this->auditTrailService->record(
            palletId: $lockedPallet->id,
            madeByUserId: $actor->id,
            eventType: $statusChanged ? AuditLog::EVENT_STATUS_CHANGED : AuditLog::EVENT_UPDATED,
            oldStatusId: $originalStatusId,
            newStatusId: $newStatus->id,
            oldClientId: $originalCustomerId,
            newClientId: $targetCustomerId,
            oldLocation: $originalLocation,
            newLocation: $newLocation,
            oldQrCode: $qrChanged ? $originalQrCode : null,
            newQrCode: $qrChanged ? $newQrCode : null,
            note: $this->normalizeNullableText($attributes['note'] ?? $attributes['reason'] ?? null),
            context: array_filter([
                'action' => $options['action'] ?? 'change_status',
                'status_changed' => $statusChanged,
                'customer_changed' => $customerChanged,
                'location_changed' => $locationChanged,
                'qr_changed' => $qrChanged,
            ], static fn ($value): bool => $value !== null),
        );

        return $lockedPallet->fresh(['user.role', 'user.customerDetail', 'currentStatus']);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function assertTransitionAllowed(
        Pallet $pallet,
        Status $newStatus,
        User $actor,
        int $targetCustomerId,
        array $options = [],
    ): void {
        if ($actor->isCustomer()) {
            if ($pallet->user_id !== $actor->id) {
                throw new AuthorizationException('You are not allowed to manage this pallet.');
            }

            if (($options['action'] ?? null) !== 'mark_ready_for_return') {
                throw new AuthorizationException('Customers can only mark their own pallets as ready for return.');
            }
        }

        if ($newStatus->slug === 'unknown' && ! $actor->isAdmin()) {
            throw new AuthorizationException('Only administrators can mark a pallet as unknown.');
        }

        if ($newStatus->slug === 'service' && ! ($options['allow_service_entry'] ?? false)) {
            throw new BadRequestHttpException('Use the service reporting endpoint to move a pallet into service.');
        }

        if ($newStatus->slug === 'pending_return' && ! in_array($pallet->currentStatus?->slug, ['at_customer', 'pending_return'], true)) {
            throw new BadRequestHttpException('Only pallets currently at the customer can be marked as ready for return.');
        }

        if ($newStatus->is_billable && $targetCustomerId <= 0) {
            throw ValidationException::withMessages([
                'customer_id' => ['A billable pallet must be assigned to a customer.'],
            ]);
        }

        $allowedTransitions = $this->allowedTransitions()[$pallet->currentStatus?->slug ?? ''] ?? [];

        if (! in_array($newStatus->slug, $allowedTransitions, true)) {
            throw new BadRequestHttpException(sprintf(
                'Transition from [%s] to [%s] is not allowed.',
                $pallet->currentStatus?->slug ?? 'unknown_state',
                $newStatus->slug,
            ));
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function allowedTransitions(): array
    {
        return [
            'bowido_warehouse' => ['bowido_warehouse', 'transport', 'at_customer', 'pending_return', 'service', 'unknown'],
            'transport' => ['bowido_warehouse', 'transport', 'at_customer', 'pending_return', 'service', 'unknown'],
            'at_customer' => ['at_customer', 'transport', 'pending_return', 'service', 'unknown'],
            'pending_return' => ['pending_return', 'transport', 'bowido_warehouse', 'service', 'unknown'],
            'service' => ['service', 'bowido_warehouse', 'transport', 'at_customer', 'unknown'],
            'unknown' => ['unknown', 'bowido_warehouse', 'transport', 'at_customer', 'pending_return', 'service'],
        ];
    }

    private function statusBySlug(string $slug): Status
    {
        /** @var Status $status */
        $status = Status::query()->where('slug', $slug)->firstOrFail();

        return $status;
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