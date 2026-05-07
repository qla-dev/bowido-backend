<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Pallet;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'pallet_id' => Pallet::factory(),
            'made_by_user_id' => User::factory()->operator(),
            'event_type' => 'status_changed',
            'note' => fake()->sentence(),
            'old_status_id' => Status::factory(),
            'new_status_id' => Status::factory(),
            'old_client_id' => null,
            'new_client_id' => null,
            'old_location' => fake()->city(),
            'new_location' => fake()->city(),
            'qr_code_version' => fake()->numberBetween(1, 10),
            'old_qr_code' => fake()->bothify('OLD-######'),
            'new_qr_code' => fake()->bothify('NEW-######'),
            'context' => ['source' => 'factory'],
        ];
    }
}
