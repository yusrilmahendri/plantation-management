<?php

namespace Database\Factories;

use App\Enums\WorkActivityStatus;
use App\Models\Plantation;
use App\Models\PlantationEntity;
use App\Models\WorkActivity;
use App\Models\WorkType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkActivity>
 */
class WorkActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plantation_entity_id' => PlantationEntity::factory(),
            'plantation_id' => Plantation::factory(),
            'work_type_id' => WorkType::factory(),
            'activity_date' => now()->toDateString(),
            'title' => 'Panen blok '.fake()->bothify('??#'),
            'status' => WorkActivityStatus::OPEN,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (WorkActivity $activity): void {
            if ($activity->plantation_id && ! $activity->plantation_entity_id) {
                $activity->plantation_entity_id = Plantation::query()->find($activity->plantation_id)?->plantation_entity_id;
            }
        });
    }
}
