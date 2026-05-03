<?php

namespace Database\Factories;

use App\Modules\GhostPalletReports\Models\GhostPalletReport;
use App\Modules\Shared\Enums\GhostPalletReportStatus;
use App\Modules\Users\Models\User;
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
            'status' => GhostPalletReportStatus::Open->value,
            'quantity' => fake()->numberBetween(1, 20),
            'location' => fake()->city(),
            'description' => fake()->sentence(),
            'notes' => fake()->sentence(),
            'paired_at' => null,
            'metadata' => ['source' => 'factory'],
        ];
    }
}
