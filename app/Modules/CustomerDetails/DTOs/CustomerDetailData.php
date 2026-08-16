<?php

namespace App\Modules\CustomerDetails\DTOs;

readonly class CustomerDetailData
{
    public function __construct(
        public int $userId,
        public string $companyName,
        public ?string $contactPerson,
        public ?string $country,
        public ?string $kvk,
        public ?string $billingEmail,
        public ?string $fixedPhone,
        public ?string $street,
        public ?string $houseNumber,
        public ?string $postalCode,
        public ?string $city,
        public ?string $warehouseScope,
        public ?string $warehouse1Street,
        public ?string $warehouse1HouseNumber,
        public ?string $warehouse1PostalCode,
        public ?string $warehouse1City,
        public ?string $warehouse2Street,
        public ?string $warehouse2HouseNumber,
        public ?string $warehouse2PostalCode,
        public ?string $warehouse2City,
        public ?string $vatNumber,
        public float $defaultPricePerDay,
        public int $gracePeriodDays,
        public ?string $notes,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            userId: (int) $attributes['user_id'],
            companyName: trim((string) $attributes['company_name']),
            contactPerson: self::nullableString($attributes['contact_person'] ?? null),
            country: isset($attributes['country']) ? trim((string) $attributes['country']) : null,
            kvk: isset($attributes['kvk']) ? trim((string) $attributes['kvk']) : ($attributes['kvk_number'] ?? null),
            billingEmail: $attributes['billing_email'] ?? null,
            fixedPhone: $attributes['fixed_phone'] ?? null,
            street: $attributes['street'] ?? null,
            houseNumber: $attributes['house_number'] ?? null,
            postalCode: $attributes['postal_code'] ?? null,
            city: $attributes['city'] ?? null,
            warehouseScope: $attributes['warehouse_scope'] ?? null,
            warehouse1Street: $attributes['warehouse1_street'] ?? null,
            warehouse1HouseNumber: $attributes['warehouse1_house_number'] ?? null,
            warehouse1PostalCode: $attributes['warehouse1_postal_code'] ?? null,
            warehouse1City: $attributes['warehouse1_city'] ?? null,
            warehouse2Street: $attributes['warehouse2_street'] ?? null,
            warehouse2HouseNumber: $attributes['warehouse2_house_number'] ?? null,
            warehouse2PostalCode: $attributes['warehouse2_postal_code'] ?? null,
            warehouse2City: $attributes['warehouse2_city'] ?? null,
            vatNumber: $attributes['vat_number'] ?? null,
            defaultPricePerDay: round((float) ($attributes['default_price_per_day'] ?? 2), 2),
            gracePeriodDays: (int) ($attributes['grace_period_days'] ?? 14),
            notes: $attributes['notes'] ?? null,
            isActive: (bool) ($attributes['is_active'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'company_name' => $this->companyName,
            'contact_person' => $this->contactPerson,
            'country' => $this->country,
            'kvk' => $this->kvk,
            'billing_email' => $this->billingEmail,
            'fixed_phone' => $this->fixedPhone,
            'street' => $this->street,
            'house_number' => $this->houseNumber,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'warehouse_scope' => $this->warehouseScope,
            'warehouse1_street' => $this->warehouse1Street,
            'warehouse1_house_number' => $this->warehouse1HouseNumber,
            'warehouse1_postal_code' => $this->warehouse1PostalCode,
            'warehouse1_city' => $this->warehouse1City,
            'warehouse2_street' => $this->warehouse2Street,
            'warehouse2_house_number' => $this->warehouse2HouseNumber,
            'warehouse2_postal_code' => $this->warehouse2PostalCode,
            'warehouse2_city' => $this->warehouse2City,
            'vat_number' => $this->vatNumber,
            'default_price_per_day' => $this->defaultPricePerDay,
            'grace_period_days' => $this->gracePeriodDays,
            'notes' => $this->notes,
            'is_active' => $this->isActive,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
