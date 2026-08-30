<?php

namespace Tests\Feature;

use App\Enums\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Models\Supplier;
use App\Models\Worker;
use App\Models\WorkType;
use Tests\TestCase;

class MasterDataCrudTest extends TestCase
{
    public function test_plantation_crud(): void
    {
        $entity = $this->entityWithAccess();

        $this->post(route('plantation.plantations.store', $entity), [
            'name' => 'Kebun Utama',
            'location' => 'Kalimantan',
            'total_area' => 120.5,
            'is_active' => 1,
        ])->assertRedirect(route('plantation.plantations.index', $entity));

        $plantation = Plantation::query()->forEntity($entity)->first();
        $this->assertNotNull($plantation);
        $this->assertNotEmpty($plantation->public_id);

        $this->put(route('plantation.plantations.update', [$entity, $plantation]), [
            'name' => 'Kebun Utama Revisi',
            'location' => 'Kalimantan',
            'total_area' => 130,
            'is_active' => 1,
        ])->assertRedirect(route('plantation.plantations.index', $entity));

        $this->assertSame('Kebun Utama Revisi', $plantation->fresh()->name);

        $this->delete(route('plantation.plantations.destroy', [$entity, $plantation]))
            ->assertRedirect(route('plantation.plantations.index', $entity));

        $this->assertDatabaseMissing('plantations', ['id' => $plantation->id]);
    }

    public function test_block_crud(): void
    {
        $entity = $this->entityWithAccess();
        $plantation = Plantation::factory()->create(['plantation_entity_id' => $entity->id]);

        $this->post(route('plantation.blocks.store', $entity), [
            'plantation_public_id' => $plantation->public_id,
            'code' => 'A01',
            'name' => 'Blok A',
            'area' => 12.25,
            'planting_year' => 2018,
            'is_active' => 1,
        ])->assertRedirect(route('plantation.blocks.index', $entity));

        $block = PlantationBlock::query()->forEntity($entity)->first();
        $this->assertSame('A01', $block->code);

        $this->put(route('plantation.blocks.update', [$entity, $block]), [
            'plantation_public_id' => $plantation->public_id,
            'code' => 'A02',
            'name' => 'Blok A2',
            'area' => 13,
            'planting_year' => 2019,
            'is_active' => 1,
        ])->assertRedirect(route('plantation.blocks.index', $entity));

        $this->assertSame('A02', $block->fresh()->code);

        $this->delete(route('plantation.blocks.destroy', [$entity, $block]))
            ->assertRedirect(route('plantation.blocks.index', $entity));

        $this->assertDatabaseMissing('plantation_blocks', ['id' => $block->id]);
    }

    public function test_worker_crud(): void
    {
        $entity = $this->entityWithAccess();

        $this->post(route('plantation.workers.store', $entity), [
            'name' => 'Budi',
            'phone' => '+628111111111',
            'employment_type' => 'harian',
            'daily_rate' => 150000,
            'is_active' => 1,
        ])->assertRedirect(route('plantation.workers.index', $entity));

        $worker = Worker::query()->forEntity($entity)->first();

        $this->put(route('plantation.workers.update', [$entity, $worker]), [
            'name' => 'Budi Santoso',
            'phone' => '+628111111111',
            'employment_type' => 'borongan',
            'daily_rate' => 160000,
            'is_active' => 1,
        ])->assertRedirect(route('plantation.workers.index', $entity));

        $this->assertSame('Budi Santoso', $worker->fresh()->name);

        $this->delete(route('plantation.workers.destroy', [$entity, $worker]))
            ->assertRedirect(route('plantation.workers.index', $entity));
    }

    public function test_work_type_crud(): void
    {
        $entity = $this->entityWithAccess();

        $this->post(route('plantation.work-types.store', $entity), [
            'name' => 'Panen',
            'default_rate' => 20000,
            'is_active' => 1,
        ])->assertRedirect(route('plantation.work-types.index', $entity));

        $workType = WorkType::query()->forEntity($entity)->first();

        $this->put(route('plantation.work-types.update', [$entity, $workType]), [
            'name' => 'Panen TBS',
            'default_rate' => 22000,
            'is_active' => 1,
        ])->assertRedirect(route('plantation.work-types.index', $entity));

        $this->assertSame('Panen TBS', $workType->fresh()->name);

        $this->delete(route('plantation.work-types.destroy', [$entity, $workType]))
            ->assertRedirect(route('plantation.work-types.index', $entity));
    }

    public function test_supplier_crud(): void
    {
        $entity = $this->entityWithAccess();

        $this->post(route('plantation.suppliers.store', $entity), [
            'name' => 'CV Sumber Tani',
            'phone' => '021123456',
            'is_active' => 1,
        ])->assertRedirect(route('plantation.suppliers.index', $entity));

        $supplier = Supplier::query()->forEntity($entity)->first();

        $this->put(route('plantation.suppliers.update', [$entity, $supplier]), [
            'name' => 'CV Sumber Tani Utama',
            'phone' => '021123456',
            'is_active' => 1,
        ])->assertRedirect(route('plantation.suppliers.index', $entity));

        $this->delete(route('plantation.suppliers.destroy', [$entity, $supplier]))
            ->assertRedirect(route('plantation.suppliers.index', $entity));
    }

    public function test_inventory_item_crud(): void
    {
        $entity = $this->entityWithAccess();

        $this->post(route('plantation.inventory-items.store', $entity), [
            'name' => 'NPK 15-15-15',
            'category' => InventoryCategory::Fertilizer->value,
            'unit' => 'kg',
            'minimum_stock' => 50,
            'is_active' => 1,
        ])->assertRedirect(route('plantation.inventory-items.index', $entity));

        $item = InventoryItem::query()->forEntity($entity)->first();
        $this->assertSame(InventoryCategory::Fertilizer, $item->category);

        $this->put(route('plantation.inventory-items.update', [$entity, $item]), [
            'name' => 'NPK 16-16-16',
            'category' => InventoryCategory::Fertilizer->value,
            'unit' => 'sak',
            'minimum_stock' => 40,
            'is_active' => 1,
        ])->assertRedirect(route('plantation.inventory-items.index', $entity));

        $this->delete(route('plantation.inventory-items.destroy', [$entity, $item]))
            ->assertRedirect(route('plantation.inventory-items.index', $entity));
    }

    public function test_negative_area_is_rejected(): void
    {
        $entity = $this->entityWithAccess();

        $this->from(route('plantation.plantations.create', $entity))
            ->post(route('plantation.plantations.store', $entity), [
                'name' => 'Kebun Invalid',
                'total_area' => -1,
                'is_active' => 1,
            ])->assertSessionHasErrors('total_area');
    }

    private function entityWithAccess(): PlantationEntity
    {
        $entity = PlantationEntity::factory()->create();
        $this->grantAccess($entity);

        return $entity;
    }
}
