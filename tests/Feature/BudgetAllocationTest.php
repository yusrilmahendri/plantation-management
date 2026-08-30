<?php

namespace Tests\Feature;

use App\Enums\BudgetItemCategory;
use App\Models\FinanceBudgetAllocation;
use App\Models\PlantationEntity;
use Illuminate\Support\Str;
use Tests\TestCase;

class BudgetAllocationTest extends TestCase
{
    public function test_upsert_requires_finance_bearer_token(): void
    {
        $this->putJson('/api/internal/budget-allocations/'.(string) Str::ulid(), $this->contractPayload())
            ->assertUnauthorized();
    }

    public function test_upsert_creates_allocation_from_finance_contract(): void
    {
        $entity = PlantationEntity::factory()->create(['finance_entity_public_id' => '01FINANCEENTITYTEST00001']);
        $budgetPublicId = (string) Str::ulid();

        $response = $this->putJson(
            '/api/internal/budget-allocations/'.$budgetPublicId,
            $this->contractPayload([
                'budget_public_id' => $budgetPublicId,
                'finance_entity_public_id' => $entity->finance_entity_public_id,
            ]),
            $this->financeHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('data.finance_budget_public_id', $budgetPublicId)
            ->assertJsonPath('data.allocated_amount', '50000000.00');

        $this->assertDatabaseHas('finance_budget_allocations', [
            'plantation_entity_id' => $entity->id,
            'finance_budget_public_id' => $budgetPublicId,
            'allocated_amount' => 50000000,
        ]);
        $this->assertDatabaseCount('finance_budget_allocations', 1);
    }

    public function test_upsert_is_idempotent(): void
    {
        $entity = PlantationEntity::factory()->create(['finance_entity_public_id' => '01FINANCEENTITYTEST00002']);
        $budgetPublicId = (string) Str::ulid();
        $payload = $this->contractPayload([
            'budget_public_id' => $budgetPublicId,
            'finance_entity_public_id' => $entity->finance_entity_public_id,
        ]);

        $this->putJson('/api/internal/budget-allocations/'.$budgetPublicId, $payload, $this->financeHeaders())->assertOk();
        $this->putJson(
            '/api/internal/budget-allocations/'.$budgetPublicId,
            array_merge($payload, ['name' => 'Anggaran Operasional September Revisi']),
            $this->financeHeaders()
        )->assertOk()->assertJsonPath('data.name', 'Anggaran Operasional September Revisi');

        $this->assertDatabaseCount('finance_budget_allocations', 1);
        $this->assertSame('Anggaran Operasional September Revisi', FinanceBudgetAllocation::query()->first()->name);
    }

    public function test_unknown_finance_entity_is_rejected(): void
    {
        $budgetPublicId = (string) Str::ulid();

        $this->putJson(
            '/api/internal/budget-allocations/'.$budgetPublicId,
            $this->contractPayload(['budget_public_id' => $budgetPublicId, 'finance_entity_public_id' => '01UNKNOWN']),
            $this->financeHeaders()
        )->assertStatus(422);
    }

    public function test_items_cannot_exceed_finance_allocation(): void
    {
        $entity = $this->entityWithAccess();
        $allocation = FinanceBudgetAllocation::factory()->create([
            'plantation_entity_id' => $entity->id,
            'allocated_amount' => 50_000_000,
        ]);

        $this->post(route('plantation.budgets.items.store', [$entity, $allocation]), [
            'category' => BudgetItemCategory::WAGES->value,
            'name' => 'Upah',
            'allocated_amount' => 15_000_000,
        ])->assertRedirect();

        $this->from(route('plantation.budgets.show', [$entity, $allocation]))
            ->post(route('plantation.budgets.items.store', [$entity, $allocation]), [
                'category' => BudgetItemCategory::FERTILIZER->value,
                'name' => 'Pupuk',
                'allocated_amount' => 40_000_000,
            ])->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, $allocation->items()->count());
    }

    public function test_realizations_cannot_silently_exceed_item_allocation(): void
    {
        $entity = $this->entityWithAccess();
        $allocation = FinanceBudgetAllocation::factory()->create([
            'plantation_entity_id' => $entity->id,
            'allocated_amount' => 50_000_000,
        ]);

        $this->post(route('plantation.budgets.items.store', [$entity, $allocation]), [
            'category' => BudgetItemCategory::FUEL->value,
            'name' => 'BBM',
            'allocated_amount' => 5_000_000,
        ]);

        $item = $allocation->items()->first();

        $this->from(route('plantation.budgets.show', [$entity, $allocation]))
            ->post(route('plantation.budgets.realizations.store', [$entity, $allocation, $item]), [
                'amount' => 6_000_000,
                'realization_date' => '2026-09-10',
            ])->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, $item->realizations()->count());
    }

    public function test_finance_cannot_shrink_allocation_below_existing_items(): void
    {
        $entity = PlantationEntity::factory()->create(['finance_entity_public_id' => '01FINANCEENTITYTEST00003']);
        $budgetPublicId = (string) Str::ulid();
        $payload = $this->contractPayload([
            'budget_public_id' => $budgetPublicId,
            'finance_entity_public_id' => $entity->finance_entity_public_id,
        ]);

        $this->putJson('/api/internal/budget-allocations/'.$budgetPublicId, $payload, $this->financeHeaders())->assertOk();

        $allocation = FinanceBudgetAllocation::query()->first();
        $this->grantAccess($entity);
        $this->post(route('plantation.budgets.items.store', [$entity, $allocation]), [
            'category' => BudgetItemCategory::WAGES->value,
            'name' => 'Upah',
            'allocated_amount' => 15_000_000,
        ]);

        $this->putJson(
            '/api/internal/budget-allocations/'.$budgetPublicId,
            array_merge($payload, ['allocated_amount' => 10_000_000]),
            $this->financeHeaders()
        )->assertStatus(422);

        $this->assertEquals(50_000_000.0, (float) $allocation->fresh()->allocated_amount);
    }

    public function test_example_split_fits_inside_finance_allocation(): void
    {
        $entity = $this->entityWithAccess();
        $allocation = FinanceBudgetAllocation::factory()->create([
            'plantation_entity_id' => $entity->id,
            'allocated_amount' => 50_000_000,
        ]);

        $splits = [
            [BudgetItemCategory::WAGES, 'Upah', 15_000_000],
            [BudgetItemCategory::FERTILIZER, 'Pupuk', 20_000_000],
            [BudgetItemCategory::FUEL, 'BBM', 5_000_000],
            [BudgetItemCategory::HERBICIDE, 'Racun', 4_000_000],
            [BudgetItemCategory::EQUIPMENT, 'Peralatan', 3_000_000],
            [BudgetItemCategory::RESERVE, 'Cadangan', 3_000_000],
        ];

        foreach ($splits as [$category, $name, $amount]) {
            $this->post(route('plantation.budgets.items.store', [$entity, $allocation]), [
                'category' => $category->value,
                'name' => $name,
                'allocated_amount' => $amount,
            ])->assertRedirect();
        }

        $this->assertSame(6, $allocation->items()->count());
        $this->assertSame('0.00', $allocation->fresh()->remainingToAllocate());
    }

    public function test_entity_cannot_open_another_entity_budget(): void
    {
        $entityA = PlantationEntity::factory()->create();
        $entityB = PlantationEntity::factory()->create();
        $this->grantAccess($entityA);
        $allocationB = FinanceBudgetAllocation::factory()->create(['plantation_entity_id' => $entityB->id]);

        $this->get(route('plantation.budgets.show', [$entityA, $allocationB]))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function contractPayload(array $overrides = []): array
    {
        return array_merge([
            'budget_public_id' => (string) Str::ulid(),
            'finance_entity_public_id' => '01FINANCEENTITYTEST00000',
            'name' => 'Anggaran Operasional September',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'allocated_amount' => 50000000,
        ], $overrides);
    }

    private function entityWithAccess(): PlantationEntity
    {
        $entity = PlantationEntity::factory()->create();
        $this->grantAccess($entity);

        return $entity;
    }
}
