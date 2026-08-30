<?php

namespace Database\Factories;

use App\Models\PlantationEntity;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Worker>
 */
class WorkerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plantation_entity_id' => PlantationEntity::factory(),
            'name' => fake()->name(),
            'phone' => fake()->optional()->numerify('08##########'),
            'address' => fake()->optional()->address(),
            'employment_type' => fake()->optional()->randomElement(['harian', 'borongan', 'tetap']),
            'daily_rate' => fake()->optional()->randomFloat(2, 50000, 250000),
            'is_active' => true,
        ];
    }
}
