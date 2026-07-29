<?php

namespace App\Modules\Pallets\Services;

use App\Modules\Invoices\Services\OverduePalletInvoiceService;
use App\Modules\Pallets\DTOs\PalletData;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Pallets\Repositories\PalletRepository;
use App\Modules\Pallets\Rules\PalletCustomerAssignmentRule;
use App\Modules\Shared\Services\BaseCrudService;
use App\Modules\Shared\Services\TrackableAssetService;
use App\Modules\Statuses\Repositories\StatusRepository;
use App\Modules\Users\Models\User;
use App\Modules\Users\Repositories\UserRepository;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PalletService extends BaseCrudService
{
    private const SERVICE_ADDRESS = 'Nikole Tesle 71, 74000 Doboj';

    public function __construct(
        private readonly PalletRepository $palletRepository,
        private readonly UserRepository $userRepository,
        private readonly StatusRepository $statusRepository,
        private readonly TrackableAssetService $trackableAssetService,
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
            $attributes['last_status_changed_at'] = now();
            $status = $this->statusRepository->findOrFail($data->currentStatusId);

            if (in_array($status->slug, ['bih-nl-transport', 'nl-bih-transport'], true)) {
                $attributes['current_location'] = 'Na putu';
            } elseif ($status->slug === 'service') {
                $attributes['current_location'] = self::SERVICE_ADDRESS;
            } elseif ($this->customerAssignmentRule->statusAllowsCustomer($status)) {
                $customer = $data->userId
                    ? User::query()->with('customerDetail')->find($data->userId)
                    : null;
                $attributes['current_location'] = $customer?->customerDetail?->warehouseOneAddress()
                    ?? $customer?->customerDetail?->businessAddress()
                    ?? '';
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

            if (in_array($nextStatus->slug, ['bih-nl-transport', 'nl-bih-transport'], true)) {
                $attributes['current_location'] = 'Na putu';
            } elseif ($nextStatus->slug === 'service') {
                $attributes['current_location'] = self::SERVICE_ADDRESS;
            }

            if ($this->customerAssignmentRule->statusAllowsCustomer($nextStatus)) {
                $customer = $data->userId
                    ? User::query()->with('customerDetail')->find($data->userId)
                    : null;
                $customerAddress = $customer?->customerDetail?->warehouseOneAddress()
                    ?? $customer?->customerDetail?->businessAddress()
                    ?? '';
                $requestedLocation = trim((string) ($attributes['current_location'] ?? ''));
                $deliveryAddress = $this->deliveryLocationAddress($lockedPallet);
                $attributes['current_location'] = in_array($nextStatus->slug, ['bij-de-klant', 'ophalen-klant'], true)
                    && $deliveryAddress !== null
                    && $requestedLocation === $deliveryAddress
                        ? $deliveryAddress
                        : $customerAddress;
            }

            $overdueInvoiceData = $this->overdueInvoiceData($lockedPallet, $data->currentStatusId);

            if ((int) $originalAttributes['current_status_id'] !== $data->currentStatusId) {
                $attributes['last_status_changed_at'] = now();
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

        if ($overdueInvoiceData !== null) {
            try {
                [, $customerSince, $graceDays, $overdueDays, $pricePerDay] = $overdueInvoiceData;
                Log::info('Automatic overdue pallet invoice check started.', [
                    'pallet_id' => $updatedPallet->id,
                    'customer_since' => $customerSince->toDateString(),
                    'grace_period_days' => $graceDays,
                    'overdue_days' => $overdueDays,
                    'price_per_day' => $pricePerDay,
                ]);
                $invoice = $this->overduePalletInvoiceService->generate($updatedPallet, ...$overdueInvoiceData);

                if ($invoice !== null) {
                    $recipient = $this->overduePalletInvoiceService->send($invoice);
                    Log::info('Automatic overdue pallet invoice delivered.', [
                        'pallet_id' => $updatedPallet->id,
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'recipient' => $recipient,
                    ]);
                } else {
                    Log::warning('Automatic overdue pallet invoice skipped: no valid recipient or positive rate.', [
                        'pallet_id' => $updatedPallet->id,
                    ]);
                }
            } catch (\Throwable $exception) {
                Log::error('Unable to deliver automatic overdue pallet invoice.', [
                    'pallet_id' => $updatedPallet->id,
                    'exception' => $exception,
                ]);
            }
        }

        return $updatedPallet;
    }

    public function updateCurrentLocation(Pallet $pallet, string $location): Pallet
    {
        return DB::transaction(function () use ($pallet, $location): Pallet {
            $lockedPallet = $this->palletRepository->lockForUpdate($pallet->id);

            /** @var Pallet $updatedPallet */
            $updatedPallet = $this->palletRepository->update($lockedPallet, [
                'current_location' => trim($location),
            ]);

            return $updatedPallet->load(['user.role', 'user.customerDetail', 'currentStatus', 'deliveryLocation']);
        });
    }

    public function updateClientStatus(Pallet $pallet, int $statusId, User $actor): Pallet
    {
        $nextStatus = $this->statusRepository->findOrFail($statusId);

        if (! $this->customerAssignmentRule->statusAllowsCustomer($nextStatus)) {
            throw ValidationException::withMessages([
                'current_status_id' => [__('Customers can only select client tracking statuses.')],
            ]);
        }

        return DB::transaction(function () use ($pallet, $statusId, $actor): Pallet {
            $lockedPallet = $this->palletRepository->lockForUpdate($pallet->id);
            $originalAttributes = $lockedPallet->only(['user_id', 'current_status_id', 'current_location', 'qr_code']);
            $attributes = [
                'user_id' => $lockedPallet->user_id,
                'current_status_id' => $statusId,
                'current_location' => $lockedPallet->current_location,
                'last_status_changed_at' => now(),
            ];

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

    public function claimCustomerPossession(
        Pallet $pallet,
        User $customer,
        int $statusId,
        string $location,
    ): Pallet {
        $status = $this->statusRepository->findOrFail($statusId);

        if (! $this->customerAssignmentRule->statusAllowsCustomer($status)) {
            throw ValidationException::withMessages([
                'current_status_id' => [__('Customers may only set Bij de klant or Ophalen klant.')],
            ]);
        }

        return DB::transaction(function () use ($pallet, $customer, $status, $location): Pallet {
            $lockedPallet = $this->palletRepository->lockForUpdate($pallet->id);
            $lockedPallet->update([
                'user_id' => $customer->id,
                'current_status_id' => $status->id,
                'current_location' => trim($location),
                'last_status_changed_at' => now(),
            ]);

            Log::info('Customer claimed pallet possession.', [
                'pallet_id' => $lockedPallet->id,
                'customer_id' => $customer->id,
                'status_id' => $status->id,
                'location' => trim($location),
            ]);

            return $lockedPallet->fresh(['user.customerDetail', 'currentStatus', 'deliveryLocation']);
        });
    }

    public function delete(int $id, ?User $actor = null): void
    {
        /** @var Pallet $pallet */
        $pallet = $this->palletRepository->findOrFail($id, $actor);

        if ($this->palletRepository->hasLinkedRecords($pallet)) {
            throw ValidationException::withMessages([
                'pallet' => [__('Pallets with linked history, reports, no-QR pairings, or invoice items cannot be deleted.')],
            ]);
        }

        $this->palletRepository->delete($pallet);
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

    private function deliveryLocationAddress(Pallet $pallet): ?string
    {
        $location = $pallet->deliveryLocation;

        if ($location === null) {
            return null;
        }

        $streetLine = trim(implode(' ', array_filter([$location->street, $location->house_number])));
        $localityLine = trim(implode(' ', array_filter([$location->postal_code, $location->city])));
        $structuredAddress = implode(', ', array_filter([$streetLine, $localityLine]));

        return $structuredAddress !== '' ? $structuredAddress : $location->formatted_address;
    }

    /**
     * @return array{0: User, 1: CarbonInterface, 2: int, 3: int, 4: float}|null
     */
    private function overdueInvoiceData(Pallet $pallet, int $nextStatusId): ?array
    {
        if (
            $pallet->currentStatus?->slug !== 'bij-de-klant'
            || $pallet->current_status_id === $nextStatusId
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
}
