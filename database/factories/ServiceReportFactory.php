<?php

namespace Database\Factories;

use App\Modules\Pallets\Models\Pallet;
use App\Modules\ServiceReports\Models\ServiceReport;
use App\Modules\Shared\Enums\ServiceReportStatus;
use App\Modules\Users\Models\User;
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
            'status' => ServiceReportStatus::Open->value,
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
