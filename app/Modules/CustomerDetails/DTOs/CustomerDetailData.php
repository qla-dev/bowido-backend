<?php

namespace App\Modules\CustomerDetails\DTOs;

readonly class CustomerDetailData
{
    public function __construct(
        public int $userId,
        public string $companyName,
        public ?string $country,
        public ?string $province,
        public ?string $kvk,
        public ?string $billingEmail,
        public ?string $fixedPhone,
        public ?string $street,
        public ?string $postalCode,
        public ?string $warehouseScope,
        public ?string $billingAddress,
        public ?string $deliveryAddress,
        public ?string $taxNumber,
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
            country: isset($attributes['country']) ? trim((string) $attributes['country']) : null,
            province: isset($attributes['province']) ? trim((string) $attributes['province']) : null,
            kvk: isset($attributes['kvk']) ? trim((string) $attributes['kvk']) : ($attributes['kvk_number'] ?? null),
            billingEmail: $attributes['billing_email'] ?? null,
            fixedPhone: $attributes['fixed_phone'] ?? null,
            street: $attributes['street'] ?? null,
            postalCode: $attributes['postal_code'] ?? null,
            warehouseScope: $attributes['warehouse_scope'] ?? null,
            billingAddress: $attributes['billing_address'] ?? null,
            deliveryAddress: $attributes['delivery_address'] ?? null,
            taxNumber: $attributes['tax_number'] ?? null,
            vatNumber: $attributes['vat_number'] ?? null,
            defaultPricePerDay: round((float) ($attributes['default_price_per_day'] ?? 0), 2),
            gracePeriodDays: (int) ($attributes['grace_period_days'] ?? 0),
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
            'country' => $this->country,
            'province' => $this->province,
            'kvk' => $this->kvk,
            'billing_email' => $this->billingEmail,
            'fixed_phone' => $this->fixedPhone,
            'street' => $this->street,
            'postal_code' => $this->postalCode,
            'warehouse_scope' => $this->warehouseScope,
            'billing_address' => $this->billingAddress,
            'delivery_address' => $this->deliveryAddress,
            'tax_number' => $this->taxNumber,
            'vat_number' => $this->vatNumber,
            'default_price_per_day' => $this->defaultPricePerDay,
            'grace_period_days' => $this->gracePeriodDays,
            'notes' => $this->notes,
            'is_active' => $this->isActive,
        ];
    }
}
