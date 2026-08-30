<?php

namespace Database\Factories;

use App\Models\PlantationEntity;
use App\Models\WorkType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkType>
 */
class WorkTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plantation_entity_id' => PlantationEntity::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'default_rate' => fake()->optional()->randomFloat(2, 10000, 150000),
            'is_active' => true,
        ];
    }
}
