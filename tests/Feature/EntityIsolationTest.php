<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Models\Supplier;
use App\Models\Worker;
use App\Models\WorkType;
use Tests\TestCase;

class EntityIsolationTest extends TestCase
{
    public function test_entity_a_cannot_open_plantation_of_entity_b(): void
    {
        [$entityA, $entityB] = $this->twoEntities();
        $plantationB = Plantation::factory()->create(['plantation_entity_id' => $entityB->id]);

        $this->get(route('plantation.plantations.edit', [$entityA, $plantationB]))
            ->assertNotFound();
    }

    public function test_entity_a_cannot_open_block_of_entity_b(): void
    {
        [$entityA, $entityB] = $this->twoEntities();
        $plantationB = Plantation::factory()->create(['plantation_entity_id' => $entityB->id]);
        $blockB = PlantationBlock::factory()->create(['plantation_id' => $plantationB->id]);

        $this->get(route('plantation.blocks.edit', [$entityA, $blockB]))
            ->assertNotFound();
    }

    public function test_entity_a_cannot_open_worker_of_entity_b(): void
    {
        [$entityA, $entityB] = $this->twoEntities();
        $workerB = Worker::factory()->create(['plantation_entity_id' => $entityB->id]);

        $this->get(route('plantation.workers.edit', [$entityA, $workerB]))
            ->assertNotFound();
    }

    public function test_entity_a_cannot_open_supplier_of_entity_b(): void
    {
        [$entityA, $entityB] = $this->twoEntities();
        $supplierB = Supplier::factory()->create(['plantation_entity_id' => $entityB->id]);

        $this->get(route('plantation.suppliers.edit', [$entityA, $supplierB]))
            ->assertNotFound();
    }

    public function test_entity_a_cannot_open_inventory_item_of_entity_b(): void
    {
        [$entityA, $entityB] = $this->twoEntities();
        $itemB = InventoryItem::factory()->create(['plantation_entity_id' => $entityB->id]);

        $this->get(route('plantation.inventory-items.edit', [$entityA, $itemB]))
            ->assertNotFound();
    }

    public function test_entity_a_cannot_open_work_type_of_entity_b(): void
    {
        [$entityA, $entityB] = $this->twoEntities();
        $workTypeB = WorkType::factory()->create(['plantation_entity_id' => $entityB->id]);

        $this->get(route('plantation.work-types.edit', [$entityA, $workTypeB]))
            ->assertNotFound();
    }

    public function test_dashboard_counts_are_scoped_to_the_active_entity(): void
    {
        [$entityA, $entityB] = $this->twoEntities();

        Plantation::factory()->create(['plantation_entity_id' => $entityA->id, 'is_active' => true]);
        Plantation::factory()->count(3)->create(['plantation_entity_id' => $entityB->id, 'is_active' => true]);

        $this->get(route('plantation.dashboard', $entityA))
            ->assertOk()
            ->assertViewHas('plantationCount', 1)
            ->assertViewHas('blockCount', 0);
    }

    /**
     * @return array{0: PlantationEntity, 1: PlantationEntity}
     */
    private function twoEntities(): array
    {
        $entityA = PlantationEntity::factory()->create();
        $entityB = PlantationEntity::factory()->create();
        $this->grantAccess($entityA);

        return [$entityA, $entityB];
    }
}
