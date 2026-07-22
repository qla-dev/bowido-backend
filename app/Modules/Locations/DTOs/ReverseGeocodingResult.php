<?php

namespace App\Modules\Locations\DTOs;

final readonly class ReverseGeocodingResult
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?string $formattedAddress,
        public ?string $street,
        public ?string $houseNumber,
        public ?string $city,
        public ?string $postalCode,
        public ?string $country,
        public ?string $countryCode,
        public string $provider,
    ) {}

    /** @return array<string, float|string|null> */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'formatted_address' => $this->formattedAddress,
            'street' => $this->street,
            'house_number' => $this->houseNumber,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
            'country_code' => $this->countryCode,
            'provider' => $this->provider,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            latitude: (float) $data['latitude'],
            longitude: (float) $data['longitude'],
            formattedAddress: self::nullableString($data['formatted_address'] ?? null),
            street: self::nullableString($data['street'] ?? null),
            houseNumber: self::nullableString($data['house_number'] ?? null),
            city: self::nullableString($data['city'] ?? null),
            postalCode: self::nullableString($data['postal_code'] ?? null),
            country: self::nullableString($data['country'] ?? null),
            countryCode: self::nullableString($data['country_code'] ?? null),
            provider: (string) $data['provider'],
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }
}
