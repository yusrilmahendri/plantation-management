<?php

namespace Database\Factories;

use App\Models\PlantationEntity;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plantation_entity_id' => PlantationEntity::factory(),
            'name' => fake()->company(),
            'phone' => fake()->optional()->numerify('+############'),
            'address' => fake()->optional()->address(),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
