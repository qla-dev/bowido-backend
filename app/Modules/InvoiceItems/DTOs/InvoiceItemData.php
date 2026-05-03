<?php

namespace App\Modules\InvoiceItems\DTOs;

use Carbon\Carbon;

readonly class InvoiceItemData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public int $invoiceId,
        public ?int $palletId,
        public string $description,
        public string $periodStart,
        public string $periodEnd,
        public int $billedDays,
        public float $pricePerDay,
        public ?array $metadata,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            invoiceId: (int) $attributes['invoice_id'],
            palletId: isset($attributes['pallet_id']) ? (int) $attributes['pallet_id'] : null,
            description: trim((string) $attributes['description']),
            periodStart: Carbon::parse($attributes['period_start'])->toDateString(),
            periodEnd: Carbon::parse($attributes['period_end'])->toDateString(),
            billedDays: (int) $attributes['billed_days'],
            pricePerDay: round((float) $attributes['price_per_day'], 2),
            metadata: isset($attributes['metadata']) && is_array($attributes['metadata']) ? $attributes['metadata'] : null,
        );
    }

    public function amount(): float
    {
        return round($this->billedDays * $this->pricePerDay, 2);
    }
}
