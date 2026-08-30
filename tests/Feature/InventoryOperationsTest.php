<?php

namespace Tests\Feature;

use App\Enums\BudgetItemCategory;
use App\Enums\InventoryCategory;
use App\Enums\InventoryDocumentStatus;
use App\Enums\RealizationSourceType;
use App\Enums\RealizationStatus;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Models\BudgetAllocationItem;
use App\Models\BudgetRealization;
use App\Models\FinanceBudgetAllocation;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\WorkActivity;
use App\Models\WorkType;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InventoryOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    public function test_create_draft_purchase_calculates_subtotal_and_adjustment(): void
    {
        [$entity, $item] = $this->entityWithItem();

        $this->post(route('plantation.purchases.store', $entity), $this->purchasePayload($item, [
            'adjustment_amount' => 5000,
            'items' => [[
                'inventory_item_public_id' => $item->public_id,
                'quantity' => 10,
                'unit_cost' => 1000,
            ]],
        ]))->assertRedirect();

        $purchase = InventoryPurchase::query()->forEntity($entity)->first();
        $this->assertSame(InventoryDocumentStatus::DRAFT, $purchase->status);
        $this->assertSame('10000.00', Money::normalize($purchase->subtotal));
        $this->assertSame('15000.00', Money::normalize($purchase->total_amount));
        Http::assertNothingSent();
    }

    public function test_foreign_supplier_is_rejected(): void
    {
        [$entity, $item] = $this->entityWithItem();
        $foreign = Supplier::factory()->create();

        $this->from(route('plantation.purchases.create', $entity))
            ->post(route('plantation.purchases.store', $entity), $this->purchasePayload($item, [
                'supplier_public_id' => $foreign->public_id,
            ]))
            ->assertSessionHasErrors('supplier_public_id');
    }

    public function test_foreign_inventory_item_is_rejected(): void
    {
        [$entity, $item] = $this->entityWithItem();
        $foreign = InventoryItem::factory()->create();

        $this->from(route('plantation.purchases.create', $entity))
            ->post(route('plantation.purchases.store', $entity), $this->purchasePayload($item, [
                'items' => [[
                    'inventory_item_public_id' => $foreign->public_id,
                    'quantity' => 1,
                    'unit_cost' => 100,
                ]],
            ]))
            ->assertSessionHasErrors('items.0.inventory_item_public_id');
    }

    public function test_quantity_must_be_positive_and_unit_cost_non_negative(): void
    {
        [$entity, $item] = $this->entityWithItem();

        $this->from(route('plantation.purchases.create', $entity))
            ->post(route('plantation.purchases.store', $entity), $this->purchasePayload($item, [
                'items' => [[
                    'inventory_item_public_id' => $item->public_id,
                    'quantity' => 0,
                    'unit_cost' => 100,
                ]],
            ]))
            ->assertSessionHasErrors('items.0.quantity');

        $this->from(route('plantation.purchases.create', $entity))
            ->post(route('plantation.purchases.store', $entity), $this->purchasePayload($item, [
                'items' => [[
                    'inventory_item_public_id' => $item->public_id,
                    'quantity' => 1,
                    'unit_cost' => -1,
                ]],
            ]))
            ->assertSessionHasErrors('items.0.unit_cost');
    }

    public function test_negative_final_total_is_rejected(): void
    {
        [$entity, $item] = $this->entityWithItem();

        $this->from(route('plantation.purchases.create', $entity))
            ->post(route('plantation.purchases.store', $entity), $this->purchasePayload($item, [
                'adjustment_amount' => -20000,
                'items' => [[
                    'inventory_item_public_id' => $item->public_id,
                    'quantity' => 1,
                    'unit_cost' => 1000,
                ]],
            ]))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('inventory_purchases', 0);
    }

    public function test_post_creates_purchase_in_and_realization_with_correct_source(): void
    {
        [$entity, $item] = $this->entityWithItem();
        $budgetItem = $this->fertilizerBudgetItem($entity, '100000.00');
        $purchase = $this->storePurchase($entity, $item, [
            'budget_allocation_item_public_id' => $budgetItem->public_id,
        ]);

        $this->post(route('plantation.purchases.post', [$entity, $purchase]))->assertRedirect();

        $purchase->refresh();
        $this->assertTrue($purchase->isPosted());
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => StockMovementType::PURCHASE_IN->value,
            'source_type' => StockSourceType::INVENTORY_PURCHASE->value,
            'source_public_id' => $purchase->public_id,
            'quantity' => 10,
        ]);
        $this->assertDatabaseHas('budget_realizations', [
            'source_type' => RealizationSourceType::INVENTORY_PURCHASE->value,
            'source_public_id' => $purchase->public_id,
            'amount' => 10000,
            'status' => RealizationStatus::ACTIVE->value,
        ]);
        $this->assertSame('10.000', Quantity::normalize(app(\App\Services\InventoryStockService::class)->currentStock($item->fresh())));
    }

    public function test_duplicate_post_does_not_duplicate_movement_or_realization(): void
    {
        [$entity, $item] = $this->entityWithItem();
        $budgetItem = $this->fertilizerBudgetItem($entity, '100000.00');
        $purchase = $this->storePurchase($entity, $item, [
            'budget_allocation_item_public_id' => $budgetItem->public_id,
        ]);

        $this->post(route('plantation.purchases.post', [$entity, $purchase]));
        $this->post(route('plantation.purchases.post', [$entity, $purchase]));

        $this->assertSame(1, StockMovement::query()->where('source_public_id', $purchase->public_id)->where('movement_type', StockMovementType::PURCHASE_IN)->count());
        $this->assertSame(1, BudgetRealization::query()->where('source_public_id', $purchase->public_id)->count());
    }

    public function test_over_budget_and_wrong_category_are_rejected(): void
    {
        [$entity, $item] = $this->entityWithItem();
        $small = $this->fertilizerBudgetItem($entity, '100.00');
        $purchase = $this->storePurchase($entity, $item, [
            'budget_allocation_item_public_id' => $small->public_id,
        ]);
        $this->from(route('plantation.purchases.show', [$entity, $purchase]))
            ->post(route('plantation.purchases.post', [$entity, $purchase]))
            ->assertSessionHas('error');
        $this->assertTrue($purchase->fresh()->isDraft());

        $fuel = $this->budgetItem($entity, BudgetItemCategory::FUEL, '100000.00');
        $purchase2 = $this->storePurchase($entity, $item, [
            'budget_allocation_item_public_id' => $fuel->public_id,
        ]);
        $this->from(route('plantation.purchases.show', [$entity, $purchase2]))
            ->post(route('plantation.purchases.post', [$entity, $purchase2]))
            ->assertSessionHas('error');
    }

    public function test_posted_financial_fields_are_locked(): void
    {
        [$entity, $item] = $this->entityWithItem();
        $purchase = $this->storePurchase($entity, $item);
        $this->post(route('plantation.purchases.post', [$entity, $purchase]));

        $this->from(route('plantation.purchases.edit', [$entity, $purchase]))
            ->put(route('plantation.purchases.update', [$entity, $purchase]), $this->purchasePayload($item, [
                'items' => [[
                    'inventory_item_public_id' => $item->public_id,
                    'quantity' => 99,
                    'unit_cost' => 1,
                ]],
            ]))
            ->assertSessionHas('error');

        $this->assertSame('10000.00', Money::normalize($purchase->fresh()->total_amount));
    }

    public function test_cancel_posted_purchase_reverses_stock_and_realization(): void
    {
        [$entity, $item] = $this->entityWithItem();
        $budgetItem = $this->fertilizerBudgetItem($entity, '100000.00');
        $purchase = $this->storePurchase($entity, $item, [
            'budget_allocation_item_public_id' => $budgetItem->public_id,
        ]);
        $this->post(route('plantation.purchases.post', [$entity, $purchase]));
        $this->post(route('plantation.purchases.cancel', [$entity, $purchase]), ['reason' => 'Salah input']);

        $purchase->refresh();
        $this->assertTrue($purchase->isCancelled());
        $this->assertSame(1, StockMovement::query()->where('source_public_id', $purchase->public_id)->where('movement_type', StockMovementType::PURCHASE_IN)->count());
        $this->assertSame(1, StockMovement::query()->where('source_public_id', $purchase->public_id)->where('movement_type', StockMovementType::RETURN_OUT)->count());
        $this->assertSame('0.000', Quantity::normalize(app(\App\Services\InventoryStockService::class)->currentStock($item->fresh())));
        $this->assertSame(RealizationStatus::REVERSED, $purchase->budgetRealization->fresh()->status);
        $this->assertSame('0.00', Money::normalize($budgetItem->fresh()->realizedTotal()));
    }

    public function test_low_stock_and_adjustments(): void
    {
        [$entity, $item] = $this->entityWithItem(['minimum_stock' => 5]);
        $stock = app(\App\Services\InventoryStockService::class);

        $this->assertTrue($stock->isLowStock($item, '0.000'));

        $this->post(route('plantation.stock-adjustments.store', $entity), [
            'inventory_item_public_id' => $item->public_id,
            'movement_type' => StockMovementType::ADJUSTMENT_IN->value,
            'quantity' => 8,
            'movement_date' => now()->toDateString(),
            'reason' => 'Koreksi awal',
        ])->assertRedirect();

        $this->assertSame('8.000', $stock->currentStock($item->fresh()));
        $this->assertFalse($stock->isLowStock($item->fresh()));

        $this->from(route('plantation.stock-adjustments.create', $entity))
            ->post(route('plantation.stock-adjustments.store', $entity), [
                'inventory_item_public_id' => $item->public_id,
                'movement_type' => StockMovementType::ADJUSTMENT_OUT->value,
                'quantity' => 20,
                'movement_date' => now()->toDateString(),
                'reason' => 'Terlalu besar',
            ])
            ->assertSessionHas('error');

        $this->post(route('plantation.stock-adjustments.store', $entity), [
            'inventory_item_public_id' => $item->public_id,
            'movement_type' => StockMovementType::ADJUSTMENT_OUT->value,
            'quantity' => 3,
            'movement_date' => now()->toDateString(),
            'reason' => 'Koreksi keluar',
        ])->assertRedirect();

        $this->assertSame('5.000', $stock->currentStock($item->fresh()));
        $this->assertTrue($stock->isLowStock($item->fresh()));
        $this->assertSame(0, BudgetRealization::query()->count());
    }

    public function test_material_usage_posts_stock_without_second_realization(): void
    {
        [$entity, $item] = $this->entityWithItem();
        $budgetItem = $this->fertilizerBudgetItem($entity, '100000.00');
        $purchase = $this->storePurchase($entity, $item, [
            'budget_allocation_item_public_id' => $budgetItem->public_id,
        ]);
        $this->post(route('plantation.purchases.post', [$entity, $purchase]));
        $plantation = Plantation::factory()->create(['plantation_entity_id' => $entity->id]);
        $block = PlantationBlock::factory()->create(['plantation_id' => $plantation->id]);

        $this->post(route('plantation.material-usages.store', $entity), [
            'plantation_public_id' => $plantation->public_id,
            'plantation_block_public_id' => $block->public_id,
            'usage_date' => now()->toDateString(),
            'items' => [[
                'inventory_item_public_id' => $item->public_id,
                'quantity' => 4,
            ]],
        ])->assertRedirect();

        $usage = \App\Models\MaterialUsage::query()->forEntity($entity)->first();
        $this->post(route('plantation.material-usages.post', [$entity, $usage]))->assertRedirect();

        $this->assertDatabaseHas('stock_movements', [
            'source_type' => StockSourceType::MATERIAL_USAGE->value,
            'source_public_id' => $usage->public_id,
            'movement_type' => StockMovementType::USAGE_OUT->value,
        ]);
        $this->assertSame(1, BudgetRealization::query()->where('status', RealizationStatus::ACTIVE)->count());
        $this->assertSame('6.000', Quantity::normalize(app(\App\Services\InventoryStockService::class)->currentStock($item->fresh())));

        $this->from(route('plantation.material-usages.show', [$entity, $usage]))
            ->post(route('plantation.material-usages.store', $entity), [
                'plantation_public_id' => $plantation->public_id,
                'usage_date' => now()->toDateString(),
                'items' => [[
                    'inventory_item_public_id' => $item->public_id,
                    'quantity' => 99,
                ]],
            ]);
        $over = \App\Models\MaterialUsage::query()->forEntity($entity)->latest('id')->first();
        $this->from(route('plantation.material-usages.show', [$entity, $over]))
            ->post(route('plantation.material-usages.post', [$entity, $over]))
            ->assertSessionHas('error');

        $this->post(route('plantation.material-usages.cancel', [$entity, $usage]), ['reason' => 'Salah blok']);
        $this->assertSame(1, StockMovement::query()->where('source_public_id', $usage->public_id)->where('movement_type', StockMovementType::RETURN_IN)->count());
        $this->assertSame('10.000', Quantity::normalize(app(\App\Services\InventoryStockService::class)->currentStock($item->fresh())));
    }

    public function test_foreign_plantation_block_is_rejected_on_usage(): void
    {
        [$entity, $item] = $this->entityWithItem();
        $plantation = Plantation::factory()->create(['plantation_entity_id' => $entity->id]);
        $foreignBlock = PlantationBlock::factory()->create();

        $this->from(route('plantation.material-usages.create', $entity))
            ->post(route('plantation.material-usages.store', $entity), [
                'plantation_public_id' => $plantation->public_id,
                'plantation_block_public_id' => $foreignBlock->public_id,
                'usage_date' => now()->toDateString(),
                'items' => [[
                    'inventory_item_public_id' => $item->public_id,
                    'quantity' => 1,
                ]],
            ])
            ->assertSessionHasErrors('plantation_block_public_id');
    }

    public function test_fertilizer_application_rules(): void
    {
        [$entity, $fertilizer] = $this->entityWithItem();
        $herbicide = InventoryItem::factory()->create([
            'plantation_entity_id' => $entity->id,
            'category' => InventoryCategory::Herbicide,
            'minimum_stock' => 0,
        ]);
        $this->post(route('plantation.purchases.store', $entity), $this->purchasePayload($fertilizer));
        $purchase = InventoryPurchase::query()->forEntity($entity)->first();
        $this->post(route('plantation.purchases.post', [$entity, $purchase]));

        $plantation = Plantation::factory()->create(['plantation_entity_id' => $entity->id]);
        $block = PlantationBlock::factory()->create(['plantation_id' => $plantation->id]);
        $foreignBlock = PlantationBlock::factory()->create();
        $foreignActivity = WorkActivity::factory()->create();

        $this->from(route('plantation.fertilizer-applications.create', $entity))
            ->post(route('plantation.fertilizer-applications.store', $entity), [
                'plantation_public_id' => $plantation->public_id,
                'plantation_block_public_id' => $block->public_id,
                'application_date' => now()->toDateString(),
                'items' => [[
                    'inventory_item_public_id' => $herbicide->public_id,
                    'quantity' => 1,
                ]],
            ])
            ->assertSessionHasErrors('items.0.inventory_item_public_id');

        $this->from(route('plantation.fertilizer-applications.create', $entity))
            ->post(route('plantation.fertilizer-applications.store', $entity), [
                'plantation_public_id' => $plantation->public_id,
                'plantation_block_public_id' => $foreignBlock->public_id,
                'application_date' => now()->toDateString(),
                'items' => [[
                    'inventory_item_public_id' => $fertilizer->public_id,
                    'quantity' => 1,
                ]],
            ])
            ->assertSessionHasErrors('plantation_block_public_id');

        $this->from(route('plantation.fertilizer-applications.create', $entity))
            ->post(route('plantation.fertilizer-applications.store', $entity), [
                'plantation_public_id' => $plantation->public_id,
                'plantation_block_public_id' => $block->public_id,
                'application_date' => now()->toDateString(),
                'work_activity_public_id' => $foreignActivity->public_id,
                'items' => [[
                    'inventory_item_public_id' => $fertilizer->public_id,
                    'quantity' => 1,
                ]],
            ])
            ->assertSessionHasErrors('work_activity_public_id');

        $workType = WorkType::factory()->create(['plantation_entity_id' => $entity->id]);
        $activity = WorkActivity::factory()->create([
            'plantation_entity_id' => $entity->id,
            'plantation_id' => $plantation->id,
            'work_type_id' => $workType->id,
        ]);

        $this->post(route('plantation.fertilizer-applications.store', $entity), [
            'plantation_public_id' => $plantation->public_id,
            'plantation_block_public_id' => $block->public_id,
            'application_date' => now()->toDateString(),
            'work_activity_public_id' => $activity->public_id,
            'items' => [[
                'inventory_item_public_id' => $fertilizer->public_id,
                'quantity' => 2,
            ]],
        ])->assertRedirect();

        $application = \App\Models\FertilizerApplication::query()->forEntity($entity)->first();
        $this->post(route('plantation.fertilizer-applications.post', [$entity, $application]))->assertRedirect();
        $this->assertSame('8.000', Quantity::normalize(app(\App\Services\InventoryStockService::class)->currentStock($fertilizer->fresh())));
        $this->assertSame(0, BudgetRealization::query()->count());

        $this->post(route('plantation.fertilizer-applications.cancel', [$entity, $application]), ['reason' => 'Salah dosis']);
        $this->assertSame('10.000', Quantity::normalize(app(\App\Services\InventoryStockService::class)->currentStock($fertilizer->fresh())));
        $this->assertSame(1, StockMovement::query()->where('source_public_id', $application->public_id)->where('movement_type', StockMovementType::RETURN_IN)->count());
    }

    public function test_manual_realization_is_reversed_not_hard_deleted(): void
    {
        $entity = $this->entityWithAccess();
        $allocation = FinanceBudgetAllocation::factory()->create([
            'plantation_entity_id' => $entity->id,
            'allocated_amount' => 50_000_000,
        ]);
        $this->post(route('plantation.budgets.items.store', [$entity, $allocation]), [
            'category' => BudgetItemCategory::OTHER->value,
            'name' => 'Lainnya',
            'allocated_amount' => 1_000_000,
        ]);
        $item = BudgetAllocationItem::query()->first();

        $this->post(route('plantation.budgets.realizations.store', [$entity, $allocation, $item]), [
            'amount' => 100_000,
            'realization_date' => '2026-08-01',
        ]);

        $realization = BudgetRealization::query()->first();
        $this->delete(route('plantation.budgets.realizations.destroy', [$entity, $allocation, $item, $realization]))
            ->assertRedirect();

        $this->assertDatabaseHas('budget_realizations', [
            'id' => $realization->id,
            'status' => RealizationStatus::REVERSED->value,
        ]);
        $this->assertSame('0.00', Money::normalize($item->fresh()->realizedTotal()));
        $this->assertSame(1, BudgetRealization::query()->count());
    }

    public function test_entity_isolation_for_inventory_resources(): void
    {
        [$entityA, $itemA] = $this->entityWithItem();
        $entityB = PlantationEntity::factory()->create();
        $itemB = InventoryItem::factory()->create(['plantation_entity_id' => $entityB->id, 'category' => InventoryCategory::Fertilizer]);
        $supplierB = Supplier::factory()->create(['plantation_entity_id' => $entityB->id]);
        $budgetB = $this->fertilizerBudgetItem($entityB, '100000.00');

        $purchaseB = $this->storePurchase($entityB, $itemB);
        $plantationB = Plantation::factory()->create(['plantation_entity_id' => $entityB->id]);
        $blockB = PlantationBlock::factory()->create(['plantation_id' => $plantationB->id]);
        $this->post(route('plantation.material-usages.store', $entityB), [
            'plantation_public_id' => $plantationB->public_id,
            'usage_date' => now()->toDateString(),
            'items' => [[
                'inventory_item_public_id' => $itemB->public_id,
                'quantity' => 1,
            ]],
        ]);
        $usageB = \App\Models\MaterialUsage::query()->forEntity($entityB)->first();
        $this->post(route('plantation.fertilizer-applications.store', $entityB), [
            'plantation_public_id' => $plantationB->public_id,
            'plantation_block_public_id' => $blockB->public_id,
            'application_date' => now()->toDateString(),
            'items' => [[
                'inventory_item_public_id' => $itemB->public_id,
                'quantity' => 1,
            ]],
        ]);
        $appB = \App\Models\FertilizerApplication::query()->forEntity($entityB)->first();

        $this->grantAccess($entityA);
        $this->get(route('plantation.purchases.show', [$entityA, $purchaseB]))->assertNotFound();
        $this->get(route('plantation.material-usages.show', [$entityA, $usageB]))->assertNotFound();
        $this->get(route('plantation.inventory-items.show', [$entityA, $itemB]))->assertNotFound();
        $this->get(route('plantation.fertilizer-applications.show', [$entityA, $appB]))->assertNotFound();

        $this->post(route('plantation.purchases.store', $entityA), $this->purchasePayload($itemA, [
            'supplier_public_id' => $supplierB->public_id,
        ]))->assertSessionHasErrors('supplier_public_id');

        $this->post(route('plantation.purchases.store', $entityA), $this->purchasePayload($itemA, [
            'budget_allocation_item_public_id' => $budgetB->public_id,
        ]))->assertSessionHasErrors('budget_allocation_item_public_id');
    }

    public function test_dashboard_and_histories_are_entity_scoped(): void
    {
        [$entityA, $itemA] = $this->entityWithItem();
        $entityB = PlantationEntity::factory()->create();
        $itemB = InventoryItem::factory()->create([
            'plantation_entity_id' => $entityB->id,
            'category' => InventoryCategory::Fertilizer,
            'is_active' => true,
        ]);
        $supplierA = Supplier::factory()->create(['plantation_entity_id' => $entityA->id]);
        $this->grantAccess($entityB);
        $purchaseB = $this->storePurchase($entityB, $itemB);
        $this->post(route('plantation.purchases.post', [$entityB, $purchaseB]));

        $this->grantAccess($entityA);
        $this->post(route('plantation.purchases.store', $entityA), $this->purchasePayload($itemA, [
            'supplier_public_id' => $supplierA->public_id,
        ]));
        $purchaseA = InventoryPurchase::query()->forEntity($entityA)->first();
        $this->post(route('plantation.purchases.post', [$entityA, $purchaseA]));

        $this->get(route('plantation.inventory-items.index', $entityA))
            ->assertOk()
            ->assertViewHas('activeItemCount', 1)
            ->assertViewHas('purchaseValueThisMonth', '10000.00');

        $this->get(route('plantation.inventory-items.show', [$entityA, $itemA]))
            ->assertOk()
            ->assertSee($purchaseA->public_id)
            ->assertDontSee($purchaseB->public_id);

        $this->get(route('plantation.suppliers.show', [$entityA, $supplierA]))
            ->assertOk()
            ->assertViewHas('purchaseCount', 1)
            ->assertDontSee($purchaseB->public_id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: PlantationEntity, 1: InventoryItem}
     */
    private function entityWithItem(array $overrides = []): array
    {
        $entity = $this->entityWithAccess();
        $item = InventoryItem::factory()->create([
            'plantation_entity_id' => $entity->id,
            'category' => InventoryCategory::Fertilizer,
            'minimum_stock' => 0,
            'unit' => 'kg',
            ...$overrides,
        ]);

        return [$entity, $item];
    }

    private function entityWithAccess(): PlantationEntity
    {
        $entity = PlantationEntity::factory()->create();
        $this->grantAccess($entity);

        return $entity;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function purchasePayload(InventoryItem $item, array $overrides = []): array
    {
        return array_merge([
            'purchase_date' => now()->toDateString(),
            'adjustment_amount' => 0,
            'items' => [[
                'inventory_item_public_id' => $item->public_id,
                'quantity' => 10,
                'unit_cost' => 1000,
            ]],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function storePurchase(PlantationEntity $entity, InventoryItem $item, array $overrides = []): InventoryPurchase
    {
        $this->grantAccess($entity);
        $this->post(route('plantation.purchases.store', $entity), $this->purchasePayload($item, $overrides))->assertRedirect();

        return InventoryPurchase::query()->forEntity($entity)->latest('id')->firstOrFail();
    }

    private function fertilizerBudgetItem(PlantationEntity $entity, string $amount): BudgetAllocationItem
    {
        return $this->budgetItem($entity, BudgetItemCategory::FERTILIZER, $amount);
    }

    private function budgetItem(PlantationEntity $entity, BudgetItemCategory $category, string $amount): BudgetAllocationItem
    {
        $this->grantAccess($entity);
        $allocation = FinanceBudgetAllocation::factory()->create([
            'plantation_entity_id' => $entity->id,
            'allocated_amount' => 50_000_000,
        ]);

        $this->post(route('plantation.budgets.items.store', [$entity, $allocation]), [
            'category' => $category->value,
            'name' => $category->label(),
            'allocated_amount' => $amount,
        ])->assertRedirect();

        return BudgetAllocationItem::query()->where('finance_budget_allocation_id', $allocation->id)->firstOrFail();
    }
}
