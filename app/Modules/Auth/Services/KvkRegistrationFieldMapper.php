<?php

namespace App\Modules\Auth\Services;

use App\Modules\CustomerDetails\Models\CustomerDetail;

class KvkRegistrationFieldMapper
{
    /** @return array<string, string> */
    public function fromCustomerDetail(CustomerDetail $detail): array
    {
        $user = $detail->user;

        return $this->withoutEmpty([
            'kvk' => $this->value($detail->kvk),
            'name' => $this->value($user?->name) ?? $this->value($detail->company_name),
            'country' => $this->countryCode($detail->country),
            'email' => $this->value($user?->email) ?? $this->value($detail->billing_email),
            'phone_number' => $this->value($user?->phone_number),
            'fixed_phone' => $this->value($detail->fixed_phone),
            'street' => $this->value($detail->street),
            'house_number' => $this->value($detail->house_number),
            'postal_code' => $this->value($detail->postal_code),
            'city' => $this->value($detail->city),
            'warehouse1_street' => $this->value($detail->warehouse1_street),
            'warehouse1_house_number' => $this->value($detail->warehouse1_house_number),
            'warehouse1_postal_code' => $this->value($detail->warehouse1_postal_code),
            'warehouse1_city' => $this->value($detail->warehouse1_city),
            'warehouse2_street' => $this->value($detail->warehouse2_street),
            'warehouse2_house_number' => $this->value($detail->warehouse2_house_number),
            'warehouse2_postal_code' => $this->value($detail->warehouse2_postal_code),
            'warehouse2_city' => $this->value($detail->warehouse2_city),
        ]);
    }

    /** @param array<string, mixed> $profile
     *  @return array<string, string> */
    public function fromKvkProfile(array $profile, string $kvk): array
    {
        $address = $this->preferredAddress($profile);
        return $this->withoutEmpty([
            'kvk' => $kvk,
            'name' => $this->firstValue([
                data_get($profile, 'naam'),
                data_get($profile, '_embedded.hoofdvestiging.eersteHandelsnaam'),
                data_get($profile, 'handelsnamen.0.naam'),
                data_get($profile, 'handelsnaam'),
            ]),
            'country' => $this->countryCode($this->firstValue([
                data_get($address, 'land'),
                data_get($address, 'landCode'),
            ])),
            'street' => $this->firstValue([
                data_get($address, 'straatnaam'),
                data_get($address, 'straat'),
            ]),
            // The registration form has one house-number field and no separate
            // addition field, so preserve the official number without merging
            // an addition into it.
            'house_number' => $this->value(data_get($address, 'huisnummer')),
            'postal_code' => $this->firstValue([
                data_get($address, 'postcode'),
                data_get($address, 'postbusnummer'),
            ]),
            'city' => $this->firstValue([
                data_get($address, 'plaats'),
                data_get($address, 'woonplaats'),
            ]),
        ]);
    }

    /** @param array<string, mixed> $profile
     *  @return array<string, mixed> */
    private function preferredAddress(array $profile): array
    {
        $addresses = data_get($profile, '_embedded.hoofdvestiging.adressen', data_get($profile, 'adressen', []));

        if (! is_array($addresses)) {
            $addresses = [];
        }

        foreach ($addresses as $address) {
            if (is_array($address) && strtolower((string) ($address['type'] ?? '')) === 'bezoekadres') {
                return $this->addressPayload($address);
            }
        }

        foreach ($addresses as $address) {
            if (is_array($address)) {
                return $this->addressPayload($address);
            }
        }

        foreach (['_embedded.hoofdvestiging.adres', 'hoofdvestiging.adres', 'adres'] as $path) {
            $address = data_get($profile, $path);
            if (is_array($address)) {
                return $this->addressPayload($address);
            }
        }

        return [];
    }

    /** @param array<string, mixed> $address
     *  @return array<string, mixed> */
    private function addressPayload(array $address): array
    {
        foreach (['binnenlandsAdres', 'buitenlandsAdres', 'adres'] as $key) {
            if (isset($address[$key]) && is_array($address[$key])) {
                return $this->addressPayload($address[$key]);
            }
        }

        return $address;
    }

    /** @param array<int, mixed> $values */
    private function firstValue(array $values): ?string
    {
        foreach ($values as $value) {
            $normalized = $this->value($value);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function value(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function countryCode(?string $country): ?string
    {
        $normalized = $this->value($country);
        if ($normalized === null) {
            return null;
        }

        return match (mb_strtolower($normalized)) {
            'nederland', 'netherlands', 'the netherlands' => 'NL',
            'belgië', 'belgie', 'belgium' => 'BE',
            'bosnië en herzegovina', 'bosnie en herzegovina', 'bosnia and herzegovina' => 'BA',
            default => strlen($normalized) === 2 ? strtoupper($normalized) : $normalized,
        };
    }

    /** @param array<string, ?string> $fields
     *  @return array<string, string> */
    private function withoutEmpty(array $fields): array
    {
        return array_filter($fields, static fn (?string $value): bool => $value !== null);
    }
}
