<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['admin', 'operator', 'customer']).'-'.fake()->unique()->lexify('????'),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'admin',
            'description' => 'Platform administrator',
        ]);
    }

    public function operator(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'operator',
            'description' => 'Operational user',
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'customer',
            'description' => 'Customer account',
        ]);
    }
}
