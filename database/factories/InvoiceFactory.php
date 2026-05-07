<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd = now()->endOfMonth()->toDateString();

        return [
            'user_id' => User::factory()->customer(),
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'status' => Invoice::STATUS_ISSUED,
            'currency' => 'EUR',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'issued_at' => now(),
            'due_at' => now()->addDays(14)->toDateString(),
            'paid_at' => null,
            'subtotal_amount' => 0,
            'total_amount' => 0,
            'notes' => fake()->sentence(),
        ];
    }
}