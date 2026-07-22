<?php

namespace Database\Factories;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerDetail>
 */
class CustomerDetailFactory extends Factory
{
    protected $model = CustomerDetail::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'company_name' => fake()->company(),
            'country' => 'NL',
            'billing_email' => fake()->companyEmail(),
            'street' => fake()->streetName(),
            'house_number' => fake()->buildingNumber(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'warehouse1_street' => fake()->streetName(),
            'warehouse1_house_number' => fake()->buildingNumber(),
            'warehouse1_postal_code' => fake()->postcode(),
            'warehouse1_city' => fake()->city(),
            'warehouse2_street' => fake()->streetName(),
            'warehouse2_house_number' => fake()->buildingNumber(),
            'warehouse2_postal_code' => fake()->postcode(),
            'warehouse2_city' => fake()->city(),
            'vat_number' => fake()->numerify('########'),
            'default_price_per_day' => fake()->randomFloat(2, 1, 25),
            'grace_period_days' => fake()->numberBetween(0, 10),
            'notes' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
