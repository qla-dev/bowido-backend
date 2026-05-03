<?php

namespace Database\Factories;

use App\Modules\InvoiceItems\Models\InvoiceItem;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Pallets\Models\Pallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'pallet_id' => Pallet::factory(),
            'description' => fake()->sentence(4),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'billed_days' => 5,
            'price_per_day' => 2.50,
            'amount' => 12.50,
            'metadata' => ['source' => 'factory'],
        ];
    }
}
