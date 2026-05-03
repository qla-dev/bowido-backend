<?php

namespace App\Modules\Invoices\DTOs;

use Carbon\Carbon;

readonly class InvoiceData
{
    public function __construct(
        public int $userId,
        public string $periodStart,
        public string $periodEnd,
        public ?string $dueAt,
        public string $currency,
        public ?string $notes,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            userId: (int) $attributes['user_id'],
            periodStart: Carbon::parse($attributes['period_start'])->toDateString(),
            periodEnd: Carbon::parse($attributes['period_end'])->toDateString(),
            dueAt: isset($attributes['due_at']) && $attributes['due_at'] !== null
                ? Carbon::parse($attributes['due_at'])->toDateString()
                : null,
            currency: strtoupper((string) ($attributes['currency'] ?? 'EUR')),
            notes: $attributes['notes'] ?? null,
        );
    }
}
