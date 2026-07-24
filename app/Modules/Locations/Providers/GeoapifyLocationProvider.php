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

        return $this->resultFromPayload($result, $latitude, $longitude);
    }

    /** @return array<int, ReverseGeocodingResult> */
    public function searchAddress(string $query, int $limit = 5): array
    {
        $apiKey = trim((string) config('location.geoapify.api_key'));

        if ($apiKey === '') {
            throw new LocationProviderException(__('Address search is not configured.'));
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout((int) config('location.geoapify.connect_timeout_seconds', 3))
                ->timeout((int) config('location.geoapify.timeout_seconds', 8))
                ->get(rtrim((string) config('location.geoapify.base_url'), '/').'/v1/geocode/search', [
                    'text' => $query,
                    'format' => 'json',
                    'limit' => min(10, max(1, $limit)),
                    'apiKey' => $apiKey,
                ]);
        } catch (ConnectionException) {
            throw new LocationProviderException(__('The address service is temporarily unavailable.'));
        }

        if ($response->status() === 429) {
            throw new LocationProviderException(__('The address service is temporarily rate limited.'));
        }

        if ($response->failed()) {
            throw new LocationProviderException(__('The address service could not search for this address.'), 502);
        }

        $results = data_get($response->json(), 'results', []);

        if (! is_array($results)) {
            throw new LocationProviderException(__('The address service returned an invalid response.'), 502);
        }

        return array_values(array_map(
            fn (array $result): ReverseGeocodingResult => $this->resultFromPayload(
                $result,
                (float) ($result['lat'] ?? 0),
                (float) ($result['lon'] ?? 0),
            ),
            array_filter($results, fn (mixed $result): bool => is_array($result)),
        ));
    }

    /** @param array<string, mixed> $result */
    private function resultFromPayload(array $result, float $latitude, float $longitude): ReverseGeocodingResult
    {
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
            // Localities such as Donja Jošanica are returned as a suburb or a
            // display-name result rather than a street. Keep that useful first
            // address line instead of leaving the editable street field blank.
            street: $this->firstAddressLine($result),
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

    /** @param array<string, mixed> $result */
    private function firstAddressLine(array $result): ?string
    {
        foreach (['street', 'suburb', 'district', 'neighbourhood', 'name', 'address_line1'] as $field) {
            $value = $this->nullableString($result[$field] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function joinAddressLines(?string $first, ?string $second): ?string
    {
        $address = implode(', ', array_filter([$first, $second]));

        return $address !== '' ? $address : null;
    }
}
