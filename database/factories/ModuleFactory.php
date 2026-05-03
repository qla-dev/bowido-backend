<?php

namespace Database\Factories;

use App\Modules\Modules\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::headline($name),
            'slug' => Str::snake(str_replace(' ', '_', $name)),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
