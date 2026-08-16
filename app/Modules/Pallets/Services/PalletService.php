<?php

namespace App\Modules\Pallets\Services;

use App\Modules\Invoices\Services\OverduePalletInvoiceService;
use App\Modules\Pallets\DTOs\PalletData;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Pallets\Repositories\PalletRepository;
use App\Modules\Pallets\Rules\PalletCustomerAssignmentRule;
use App\Modules\Shared\Enums\AuditEventType;
use App\Modules\Shared\Services\AuditTrailService;
use App\Modules\Shared\Services\BaseCrudService;
use App\Modules\Shared\Services\TrackableAssetService;
use App\Modules\Statuses\Repositories\StatusRepository;
use App\Modules\Statuses\Models\Status;
use App\Modules\Users\Models\User;
use App\Modules\Users\Repositories\UserRepository;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PalletService extends BaseCrudService
{
    public function __construct(
        private readonly PalletRepository $palletRepository,
        private readonly UserRepository $userRepository,
        private readonly StatusRepository $statusRepository,
        private readonly TrackableAssetService $trackableAssetService,
        private readonly AuditTrailService $auditTrailService,
        private readonly PalletCustomerAssignmentRule $customerAssignmentRule,
        private readonly OverduePalletInvoiceService $overduePalletInvoiceService,
    ) {
        parent::__construct($palletRepository);
    }

    public function create(PalletData $data, User $actor): Pallet
    {
        $this->ensureDependenciesAreActive($data);

        return DB::transaction(function () use ($data): Pallet {
            $attributes = $data->toArray();
            $attributes['user_id'] = $this->normalizedCustomerId($data);
            $changedAt = now();
            $attributes['last_status_changed_at'] = $changedAt;
            $status = $this->statusRepository->findOrFail($data->currentStatusId);
            $attributes = [...$attributes, ...$this->customerTimerAttributes(new Pallet, $status, $changedAt)];

            if (in_array($status->slug, ['bih-nl-transport', 'nl-bih-transport'], true)) {
                $attributes['current_location'] = 'Na putu';
            }

            if ($this->isUnknownStatus($status)) {
                $attributes['current_location'] = null;
            }

            /** @var Pallet $pallet */
            $pallet = $this->palletRepository->create($attributes);

            return $pallet->load(['user.role', 'user.customerDetail', 'currentStatus', 'deliveryLocation']);
        });
    }

    public function update(Pallet $pallet, PalletData $data, User $actor): Pallet
    {
        $this->ensureDependenciesAreActive($data);

        $overdueInvoiceData = null;

        $updatedPallet = DB::transaction(function () use ($pallet, $data, $actor, &$overdueInvoiceData): Pallet {
            $lockedPallet = $this->palletRepository->lockForUpdate($pallet->id);
            $lockedPallet->loadMissing(['user.customerDetail', 'currentStatus', 'deliveryLocation']);
            $originalAttributes = $lockedPallet->only(['user_id', 'current_status_id', 'current_location', 'qr_code']);
            $attributes = $data->toArray();
            $attributes['user_id'] = $this->normalizedCustomerId($data);
            $nextStatus = $this->statusRepository->findOrFail($data->currentStatusId);
            $statusChanged = (int) $originalAttributes['current_status_id'] !== $data->currentStatusId;
            $customerChanged = (int) ($originalAttributes['user_id'] ?? 0) !== (int) ($attributes['user_id'] ?? 0);

            if (in_array($nextStatus->slug, ['bih-nl-transport', 'nl-bih-transport'], true)) {
                $attributes['current_location'] = 'Na putu';
            }

            if ($this->isUnknownStatus($nextStatus)) {
                $attributes['current_location'] = null;
                $lockedPallet->deliveryLocation()->delete();
            }

            $overdueInvoiceData = $this->overdueInvoiceData($lockedPallet, $data->currentStatusId);

            if ($statusChanged) {
                $changedAt = now();
                $attributes['last_status_changed_at'] = $changedAt;
                $attributes = [...$attributes, ...$this->customerTimerAttributes($lockedPallet, $nextStatus, $changedAt)];
            } elseif ($customerChanged && $this->customerAssignmentRule->isAtCustomer($nextStatus)) {
                // Assigning a different customer while the pallet is already
                // at a customer starts a new possession period. This refreshes
                // the return-by and deadline calculations using that client's
                // grace period without requiring a redundant status change.
                $changedAt = now();
                $attributes['last_status_changed_at'] = $changedAt;
                $attributes['customer_timer_started_at'] = $changedAt;
                $attributes['customer_timer_frozen_at'] = null;
            }

            /** @var Pallet $updatedPallet */
            $updatedPallet = $this->palletRepository->update($lockedPallet, $attributes);

            $this->trackableAssetService->recordMutations(
                pallet: $updatedPallet,
                originalAttributes: $originalAttributes,
                attributes: $attributes,
                actor: $actor,
            );

            return $updatedPallet->load(['user.role', 'user.customerDetail', 'currentStatus', 'deliveryLocation']);
        });

        $this->createAutomaticOverdueInvoice($updatedPallet, $overdueInvoiceData);

        return $updatedPallet;
    }

    public function updateCurrentLocation(Pallet $pallet, string $location, User $actor): Pallet
    {
        return DB::transaction(function () use ($pallet, $location, $actor): Pallet {
            $lockedPallet = $this->palletRepository->lockForUpdate($pallet->id);
            $lockedPallet->loadMissing('currentStatus');

            if ($this->isUnknownStatus($lockedPallet->currentStatus)) {
                throw ValidationException::withMessages([
                    'current_location' => [__('Unknown pallets cannot have a location.')],
                ]);
            }

            $originalAttributes = $lockedPallet->only(['user_id', 'current_status_id', 'current_location', 'qr_code']);
            $attributes = ['current_location' => trim($location)];

            /** @var Pallet $updatedPallet */
            $updatedPallet = $this->palletRepository->update($lockedPallet, $attributes);

            $this->trackableAssetService->recordMutations(
                pallet: $updatedPallet,
                originalAttributes: $originalAttributes,
                attributes: $attributes,
                actor: $actor,
            );

            return $updatedPallet->load(['user.role', 'user.customerDetail', 'currentStatus', 'deliveryLocation']);
        });
    }

    public function updateRepairStatus(Pallet $pallet, bool $isForRepair, User $actor): Pallet
    {
        return DB::transaction(function () use ($pallet, $isForRepair, $actor): Pallet {
            $lockedPallet = $this->palletRepository->lockForUpdate($pallet->id);
            $wasForRepair = $lockedPallet->is_for_repair;

            if ($wasForRepair === $isForRepair) {
                return $lockedPallet->load(['user.role', 'user.customerDetail', 'currentStatus', 'deliveryLocation']);
            }

            $actorName = trim((string) ($actor->name ?: $actor->email));
            // Keep the pallet note canonical; the frontend translates it for the currently selected language.
            $serviceNote = $isForRepair
                ? "{$actorName} admitted pallet to service."
                : "{$actorName} removed pallet from service.";
            $auditNote = $isForRepair
                ? __(':actor admitted pallet to service.', ['actor' => $actorName])
                : __(':actor removed pallet from service.', ['actor' => $actorName]);
            $existingNotes = preg_split('/\R/', trim((string) $lockedPallet->notes)) ?: [];
            $notes = implode("\n", array_slice(array_filter([
                $serviceNote,
                ...$existingNotes,
            ], static fn (string $note): bool => trim($note) !== ''), 0, 1));

            /** @var Pallet $updatedPallet */
            $updatedPallet = $this->palletRepository->update($lockedPallet, [
                'is_for_repair' => $isForRepair,
                'notes' => $notes,
            ]);

            $this->auditTrailService->record(
                palletId: $updatedPallet->id,
                madeByUserId: $actor->id,
                eventType: AuditEventType::RepairStatusChanged,
                note: $auditNote,
                context: [
                    'old_is_for_repair' => $wasForRepair,
                    'new_is_for_repair' => $isForRepair,
                    'actor_name' => $actorName,
                ],
            );

            return $updatedPallet->load(['user.role', 'user.customerDetail', 'currentStatus', 'deliveryLocation']);
        });
    }

    public function updateClientStatus(Pallet $pallet, int $statusId, User $actor, ?string $location = null): Pallet
    {
        $nextStatus = $this->statusRepository->findOrFail($statusId);

        if (! $this->customerAssignmentRule->statusAllowsCustomer($nextStatus)) {
            throw ValidationException::withMessages([
                'current_status_id' => [__('Customers can only select client tracking statuses.')],
            ]);
        }

        $overdueInvoiceData = null;

        $updatedPallet = DB::transaction(function () use ($pallet, $statusId, $nextStatus, $actor, $location, &$overdueInvoiceData): Pallet {
            $lockedPallet = $this->palletRepository->lockForUpdate($pallet->id);
            $lockedPallet->loadMissing(['user.customerDetail', 'currentStatus']);
            $originalAttributes = $lockedPallet->only(['user_id', 'current_status_id', 'current_location', 'qr_code']);
            $requestedLocation = trim((string) $location);
            $statusChanged = (int) $lockedPallet->current_status_id !== $statusId;
            $overdueInvoiceData = $this->overdueInvoiceData($lockedPallet, $statusId);
            $attributes = [
                'user_id' => $lockedPallet->user_id,
                'current_status_id' => $statusId,
                'current_location' => $requestedLocation !== ''
                    ? $requestedLocation
                    : $lockedPallet->current_location,
            ];

            if ($statusChanged) {
                $changedAt = now();
                $attributes['last_status_changed_at'] = $changedAt;
                $attributes = [...$attributes, ...$this->customerTimerAttributes($lockedPallet, $nextStatus, $changedAt)];
            }

            /** @var Pallet $updatedPallet */
            $updatedPallet = $this->palletRepository->update($lockedPallet, $attributes);

            $this->trackableAssetService->recordMutations(
                pallet: $updatedPallet,
                originalAttributes: $originalAttributes,
                attributes: $attributes,
                actor: $actor,
            );

            return $updatedPallet->load(['user.role', 'user.customerDetail', 'currentStatus', 'deliveryLocation']);
        });

        $this->createAutomaticOverdueInvoice($updatedPallet, $overdueInvoiceData);

        return $updatedPallet;
    }

    public function claimCustomerPossession(
        Pallet $pallet,
        User $customer,
        int $statusId,
        ?string $location,
    ): Pallet {
        $status = $this->statusRepository->findOrFail($statusId);

        if (! $this->customerAssignmentRule->statusAllowsCustomer($status)) {
            throw ValidationException::withMessages([
                'current_status_id' => [__('Customers may only set Bij de klant or Ophalen klant.')],
            ]);
        }

        return DB::transaction(function () use ($pallet, $customer, $status, $location): Pallet {
            $lockedPallet = $this->palletRepository->lockForUpdate($pallet->id);
            $changedAt = now();
            $trimmedLocation = $location === null ? null : trim($location);
            $nextLocation = filled($trimmedLocation)
                ? $trimmedLocation
                : $lockedPallet->current_location;
            $lockedPallet->update([
                'user_id' => $customer->id,
                'current_status_id' => $status->id,
                'current_location' => $nextLocation,
                'last_status_changed_at' => $changedAt,
                ...$this->customerTimerAttributes($lockedPallet, $status, $changedAt),
            ]);

            Log::info('Customer claimed pallet possession.', [
                'pallet_id' => $lockedPallet->id,
                'customer_id' => $customer->id,
                'status_id' => $status->id,
                'location' => $nextLocation,
            ]);

            return $lockedPallet->fresh(['user.customerDetail', 'currentStatus', 'deliveryLocation']);
        });
    }

    public function delete(int $id, ?User $actor = null): void
    {
        /** @var Pallet $pallet */
        $pallet = $this->palletRepository->findOrFail($id, $actor);

        if ($pallet->is_ghost) {
            $this->deleteNoQrPallet($pallet);

            return;
        }

        if ($this->palletRepository->hasLinkedRecords($pallet)) {
            throw ValidationException::withMessages([
                'pallet' => [__('Pallets with linked history, reports, no-QR pairings, or invoice items cannot be deleted.')],
            ]);
        }

        $this->palletRepository->delete($pallet);
    }

    /**
     * No-QR pallets are temporary return/report records. Unlike tracked
     * pallets, their report photo, delivery location, and audit trail belong
     * exclusively to the record being removed and must not prevent a return.
     */
    private function deleteNoQrPallet(Pallet $pallet): void
    {
        if ($pallet->serviceReports()->exists() || $pallet->invoiceItems()->exists()) {
            throw ValidationException::withMessages([
                'pallet' => [__('Pallets with linked service reports or invoice items cannot be deleted.')],
            ]);
        }

        DB::transaction(function () use ($pallet): void {
            $lockedPallet = $this->palletRepository->lockForUpdate($pallet->id);

            $lockedPallet->photos()->delete();
            $lockedPallet->deliveryLocation()->delete();
            $lockedPallet->auditLogs()->delete();

            // ghost_pallet_reports.paired_pallet_id is null-on-delete, so the
            // report stays available while its removed pallet link is cleared.
            $this->palletRepository->delete($lockedPallet);
        });
    }

    private function ensureDependenciesAreActive(PalletData $data): void
    {
        $status = $this->statusRepository->findOrFail($data->currentStatusId);

        if ($data->userId !== null && $this->customerAssignmentRule->statusAllowsCustomer($status)) {
            /** @var User $user */
            $user = $this->userRepository->findOrFail($data->userId);
        }

        if (isset($user) && ! $user->is_active) {
            throw ValidationException::withMessages([
                'user_id' => [__('The selected user is not active.')],
            ]);
        }

        if (isset($user) && ! $user->isCustomer()) {
            throw ValidationException::withMessages([
                'user_id' => [__('The selected user must be a customer.')],
            ]);
        }

        if (! $status->is_active) {
            throw ValidationException::withMessages([
                'current_status_id' => [__('The selected status is not active.')],
            ]);
        }
    }

    private function normalizedCustomerId(PalletData $data): ?int
    {
        $status = $this->statusRepository->findOrFail($data->currentStatusId);

        return $this->customerAssignmentRule->statusAllowsCustomer($status) ? $data->userId : null;
    }

    private function isUnknownStatus(?Status $status): bool
    {
        return $status?->slug === 'onbekend';
    }

    /**
     * The return and deadline counters measure one customer possession period.
     * Once a customer requests pickup, preserve both its start and finish so
     * later reads cannot keep increasing the counter from the new status date.
     *
     * @return array<string, CarbonInterface|null>
     */
    private function customerTimerAttributes(Pallet $pallet, Status $nextStatus, CarbonInterface $changedAt): array
    {
        $currentSlug = $pallet->currentStatus?->slug;

        if (
            $this->customerAssignmentRule->isAtCustomer($nextStatus)
            && ! $this->customerAssignmentRule->isAtCustomer($currentSlug)
        ) {
            return [
                'customer_timer_started_at' => $changedAt,
                'customer_timer_frozen_at' => null,
            ];
        }

        if (
            $this->customerAssignmentRule->isAtCustomer($currentSlug)
            && $this->customerAssignmentRule->isCustomerPickup($nextStatus)
        ) {
            return [
                // Legacy pallets did not yet have a dedicated start time.
                'customer_timer_started_at' => $pallet->customer_timer_started_at ?? $pallet->last_status_changed_at ?? $changedAt,
                'customer_timer_frozen_at' => $changedAt,
            ];
        }

        return [];
    }

    /**
     * @return array{0: User, 1: CarbonInterface, 2: int, 3: int, 4: float}|null
     */
    private function overdueInvoiceData(Pallet $pallet, int $nextStatusId): ?array
    {
        $nextStatus = $this->statusRepository->findOrFail($nextStatusId);

        if (
            ! $this->customerAssignmentRule->isAtCustomer($pallet->currentStatus)
            || ! $this->customerAssignmentRule->isCustomerPickup($nextStatus)
            || ! $pallet->user instanceof User
            || ! $pallet->last_status_changed_at
        ) {
            return null;
        }

        $graceDays = $pallet->user->customerDetail?->grace_period_days ?? $pallet->currentStatus->grace_period_days ?? 0;
        $daysAtCustomer = $pallet->last_status_changed_at->copy()->startOfDay()->diffInDays(now()->startOfDay());
        $overdueDays = max(0, $daysAtCustomer - $graceDays);

        if ($overdueDays === 0) {
            return null;
        }

        $pricePerDay = (float) ($pallet->user->customerDetail?->default_price_per_day ?? $pallet->currentStatus->price_per_day ?? 0);

        return [$pallet->user, $pallet->last_status_changed_at, $graceDays, $overdueDays, $pricePerDay];
    }

    /**
     * @param  array{0: User, 1: CarbonInterface, 2: int, 3: int, 4: float}|null  $overdueInvoiceData
     */
    private function createAutomaticOverdueInvoice(Pallet $pallet, ?array $overdueInvoiceData): void
    {
        if ($overdueInvoiceData === null) {
            return;
        }

        try {
            [, $customerSince, $graceDays, $overdueDays, $pricePerDay] = $overdueInvoiceData;
            Log::info('Automatic monthly pallet invoice creation started.', [
                'pallet_id' => $pallet->id,
                'customer_since' => $customerSince->toDateString(),
                'grace_period_days' => $graceDays,
                'overdue_days' => $overdueDays,
                'price_per_day' => $pricePerDay,
            ]);
            $invoice = $this->overduePalletInvoiceService->generate($pallet, ...$overdueInvoiceData);

            if ($invoice !== null) {
                Log::info('Automatic pallet invoice row saved on the customer monthly invoice.', [
                    'pallet_id' => $pallet->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ]);
            } else {
                Log::warning('Automatic monthly pallet invoice skipped: no overdue amount or daily rate.', [
                    'pallet_id' => $pallet->id,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Unable to create automatic monthly pallet invoice.', [
                'pallet_id' => $pallet->id,
                'exception' => $exception,
            ]);
        }
    }
}
