<?php

namespace Database\Factories;

use App\Modules\Pallets\Models\Pallet;
use App\Modules\Statuses\Models\Status;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pallet>
 */
class PalletFactory extends Factory
{
    protected $model = Pallet::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'current_status_id' => Status::factory(),
            'asset_type' => 'pallet',
            'qr_code' => fake()->unique()->bothify('PALLET-########'),
            'reference_code' => fake()->optional()->bothify('REF-######'),
            'current_location' => fake()->city(),
            'notes' => fake()->sentence(),
            'last_status_changed_at' => now(),
            'is_active' => true,
            'is_ghost' => false,
            'metadata' => ['source' => 'factory'],
        ];
    }
}
