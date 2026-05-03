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
            'billing_email' => fake()->companyEmail(),
            'billing_address' => fake()->address(),
            'delivery_address' => fake()->address(),
            'tax_number' => fake()->numerify('########'),
            'vat_number' => fake()->numerify('########'),
            'default_price_per_day' => fake()->randomFloat(2, 1, 25),
            'grace_period_days' => fake()->numberBetween(0, 10),
            'notes' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
