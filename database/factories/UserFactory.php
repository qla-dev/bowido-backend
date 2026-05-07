<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone_number' => '+387'.fake()->unique()->numerify('6########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'last_login_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::query()->firstOrCreate(
                ['name' => 'admin'],
                ['description' => 'Platform administrator', 'is_active' => true],
            )->id,
        ]);
    }

    public function operator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::query()->firstOrCreate(
                ['name' => 'operator'],
                ['description' => 'Operational user', 'is_active' => true],
            )->id,
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::query()->firstOrCreate(
                ['name' => 'customer'],
                ['description' => 'Customer account', 'is_active' => true],
            )->id,
        ]);
    }
}
