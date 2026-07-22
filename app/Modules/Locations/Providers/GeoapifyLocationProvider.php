<?php

namespace App\Modules\Locations\Providers;

use App\Modules\Locations\Contracts\LocationProviderInterface;
use App\Modules\Locations\DTOs\ReverseGeocodingResult;
use App\Modules\Locations\Exceptions\LocationProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GeoapifyLocationProvider implements LocationProviderInterface
{
    public function name(): string
    {
        return 'geoapify';
    }

    public function reverseGeocode(float $latitude, float $longitude): ReverseGeocodingResult
    {
        $apiKey = trim((string) config('location.geoapify.api_key'));

        if ($apiKey === '') {
            throw new LocationProviderException(__('Reverse geocoding is not configured.'));
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout((int) config('location.geoapify.connect_timeout_seconds', 3))
                ->timeout((int) config('location.geoapify.timeout_seconds', 8))
                ->get(rtrim((string) config('location.geoapify.base_url'), '/').'/v1/geocode/reverse', [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'json',
                    'apiKey' => $apiKey,
                ]);
        } catch (ConnectionException) {
            throw new LocationProviderException(__('The address service is temporarily unavailable.'));
        }

        if ($response->status() === 429) {
            throw new LocationProviderException(__('The address service is temporarily rate limited.'));
        }

        if ($response->failed()) {
            throw new LocationProviderException(__('The address service could not resolve this position.'), 502);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new LocationProviderException(__('The address service returned an invalid response.'), 502);
        }

        $result = data_get($payload, 'results.0');

        if (! is_array($result)) {
            return new ReverseGeocodingResult(
                latitude: $latitude,
                longitude: $longitude,
                formattedAddress: null,
                street: null,
                houseNumber: null,
                city: null,
                postalCode: null,
                country: null,
                countryCode: null,
                provider: $this->name(),
            );
        }

        $formattedAddress = $this->nullableString($result['formatted'] ?? null);

        if ($formattedAddress === null) {
            $formattedAddress = $this->joinAddressLines(
                $this->nullableString($result['address_line1'] ?? null),
                $this->nullableString($result['address_line2'] ?? null),
            );
        }

        return new ReverseGeocodingResult(
            latitude: $latitude,
            longitude: $longitude,
            formattedAddress: $formattedAddress,
            street: $this->nullableString($result['street'] ?? null),
            houseNumber: $this->nullableString($result['housenumber'] ?? null),
            city: $this->nullableString(
                $result['city'] ?? $result['town'] ?? $result['village'] ?? $result['municipality'] ?? null,
            ),
            postalCode: $this->nullableString($result['postcode'] ?? null),
            country: $this->nullableString($result['country'] ?? null),
            countryCode: $this->nullableString($result['country_code'] ?? null),
            provider: $this->name(),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function joinAddressLines(?string $first, ?string $second): ?string
    {
        $address = implode(', ', array_filter([$first, $second]));

        return $address !== '' ? $address : null;
    }
}
