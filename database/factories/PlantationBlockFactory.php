<?php

namespace Database\Factories;

use App\Models\Plantation;
use App\Models\PlantationBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlantationBlock>
 */
class PlantationBlockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plantation_id' => Plantation::factory(),
            'code' => strtoupper(fake()->unique()->bothify('B-##??')),
            'name' => fake()->optional()->word(),
            'area' => fake()->optional()->randomFloat(2, 0.5, 50),
            'crop_type' => fake()->optional()->randomElement(['Sawit', 'Karet', 'Kopi']),
            'planting_year' => fake()->optional()->numberBetween(2000, now()->year),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
