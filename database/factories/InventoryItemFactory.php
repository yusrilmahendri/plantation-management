<?php

namespace Database\Factories;

use App\Enums\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\PlantationEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plantation_entity_id' => PlantationEntity::factory(),
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement(InventoryCategory::cases()),
            'unit' => fake()->randomElement(['kg', 'liter', 'sak', 'unit']),
            'minimum_stock' => fake()->optional()->randomFloat(2, 0, 100),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
