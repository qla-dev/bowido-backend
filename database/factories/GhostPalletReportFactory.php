<?php

namespace Database\Factories;

use App\Models\GhostPalletReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GhostPalletReport>
 */
class GhostPalletReportFactory extends Factory
{
    protected $model = GhostPalletReport::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'paired_pallet_id' => null,
            'status' => GhostPalletReport::STATUS_OPEN,
            'quantity' => fake()->numberBetween(1, 20),
            'location' => fake()->city(),
            'description' => fake()->sentence(),
            'notes' => fake()->sentence(),
            'reported_at' => now(),
            'paired_at' => null,
            'metadata' => ['source' => 'factory'],
        ];
    }
}