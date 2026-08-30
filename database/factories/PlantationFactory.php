<?php

namespace Database\Factories;

use App\Models\Plantation;
use App\Models\PlantationEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plantation>
 */
class PlantationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plantation_entity_id' => PlantationEntity::factory(),
            'name' => fake()->unique()->words(2, true),
            'location' => fake()->optional()->city(),
            'total_area' => fake()->optional()->randomFloat(2, 1, 500),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
