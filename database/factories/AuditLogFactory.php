<?php

namespace Database\Factories;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Statuses\Models\Status;
use App\Modules\Users\Models\User;
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
            'old_qr_code' => fake()->bothify('OLD-######'),
            'new_qr_code' => fake()->bothify('NEW-######'),
            'context' => ['source' => 'factory'],
        ];
    }
}
