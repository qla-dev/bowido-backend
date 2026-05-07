<?php

namespace Database\Factories;

use App\Models\Pallet;
use App\Models\ServiceReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceReport>
 */
class ServiceReportFactory extends Factory
{
    protected $model = ServiceReport::class;

    public function definition(): array
    {
        return [
            'pallet_id' => Pallet::factory(),
            'reported_by_user_id' => User::factory()->operator(),
            'resolved_by_user_id' => null,
            'status' => ServiceReport::STATUS_OPEN,
            'severity' => fake()->randomElement(['low', 'medium', 'high']),
            'issue_type' => fake()->randomElement(['damage', 'defect', 'inspection']),
            'description' => fake()->paragraph(),
            'resolution_note' => null,
            'image_path' => null,
            'resolved_at' => null,
            'metadata' => ['source' => 'factory'],
        ];
    }
}