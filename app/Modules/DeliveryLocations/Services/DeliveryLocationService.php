<?php

namespace App\Modules\DeliveryLocations\Services;

use App\Modules\DeliveryLocations\Models\DeliveryLocation;
use App\Modules\Locations\Exceptions\LocationProviderException;
use App\Modules\Locations\Services\ReverseGeocodingService;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DeliveryLocationService
{
    public function __construct(private readonly ReverseGeocodingService $reverseGeocodingService) {}

    /** @param array<string, mixed> $data */
    public function upsert(Pallet $pallet, array $data, User $actor): DeliveryLocation
    {
        $latitude = (float) $data['latitude'];
        $longitude = (float) $data['longitude'];

        try {
            $address = $this->reverseGeocodingService->reverseGeocode($latitude, $longitude);
        } catch (LocationProviderException) {
            $address = null;
        }

        $street = $this->addressValue($data, 'street', $address?->street);
        $houseNumber = $this->addressValue($data, 'house_number', $address?->houseNumber);
        $postalCode = $this->addressValue($data, 'postal_code', $address?->postalCode);
        $city = $this->addressValue($data, 'city', $address?->city);
        $hasUserAddress = count(array_intersect(['street', 'house_number', 'postal_code', 'city'], array_keys($data))) > 0;
        $formattedAddress = $hasUserAddress
            ? $this->formatAddress($street, $houseNumber, $postalCode, $city) ?: $address?->formattedAddress
            : $address?->formattedAddress;

        return DB::transaction(function () use ($pallet, $data, $actor, $latitude, $longitude, $address, $street, $houseNumber, $postalCode, $city, $formattedAddress): DeliveryLocation {
            $lockedPallet = Pallet::query()->lockForUpdate()->findOrFail($pallet->id);
            $lockedPallet->loadMissing('currentStatus');

            if ($lockedPallet->currentStatus?->slug === 'onbekend') {
                throw ValidationException::withMessages([
                    'location' => [__('Unknown pallets cannot have a location.')],
                ]);
            }

            $location = DeliveryLocation::query()->updateOrCreate(
                ['pallet_id' => $lockedPallet->id],
                [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'accuracy_meters' => isset($data['accuracy_meters']) ? (float) $data['accuracy_meters'] : null,
                    'formatted_address' => $formattedAddress,
                    'street' => $street,
                    'house_number' => $houseNumber,
                    'city' => $city,
                    'postal_code' => $postalCode,
                    'country' => $address?->country,
                    'country_code' => $address?->countryCode,
                    'provider' => $address?->provider,
                    'source' => 'device_gps',
                    'confirmed_by_user' => true,
                    'created_by_user_id' => $actor->id,
                    'captured_at' => $data['captured_at'] ?? now(),
                ],
            );

            $lockedPallet->loadMissing('currentStatus');
            if ($formattedAddress !== null) {
                $lockedPallet->update(['current_location' => $formattedAddress]);
            }

            Log::info('Pallet current location persisted from map.', [
                'pallet_id' => $lockedPallet->id,
                'current_location' => $formattedAddress,
                'actor_id' => $actor->id,
            ]);

            return $location->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    private function addressValue(array $data, string $key, ?string $providerValue): ?string
    {
        if (! array_key_exists($key, $data)) {
            return $providerValue;
        }

        $value = trim((string) ($data[$key] ?? ''));

        return $value !== '' ? $value : null;
    }

    private function formatAddress(
        ?string $street,
        ?string $houseNumber,
        ?string $postalCode,
        ?string $city,
    ): ?string {
        $streetLine = trim(implode(' ', array_filter([$street, $houseNumber])));
        $localityLine = trim(implode(' ', array_filter([$postalCode, $city])));
        $formatted = implode(', ', array_filter([$streetLine, $localityLine]));

        return $formatted !== '' ? $formatted : null;
    }
}
