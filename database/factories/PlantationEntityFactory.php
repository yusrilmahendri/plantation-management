<?php

namespace Database\Factories;

use App\Models\PlantationEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlantationEntity>
 */
class PlantationEntityFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => PlantationEntity::generateUniqueSlug($name),
            'finance_entity_public_id' => (string) fake()->unique()->bothify('fin_########'),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
