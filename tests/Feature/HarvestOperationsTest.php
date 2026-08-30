<?php

namespace Tests\Feature;

use App\Enums\Commodity;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\ProductionDocumentStatus;
use App\Enums\SalePaymentStatus;
use App\Models\Buyer;
use App\Models\Harvest;
use App\Models\HarvestSale;
use App\Models\HarvestSalePayment;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HarvestOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    public function test_harvest_draft_post_and_availability(): void
    {
        [$entity, $plantation, $block] = $this->location();

        $this->post(route('plantation.harvests.store', $entity), $this->harvestPayload($plantation, $block, [
            'quantity' => 5000,
        ]))->assertRedirect();

        $harvest = Harvest::query()->forEntity($entity)->first();
        $this->assertTrue($harvest->isDraft());
        $this->assertSame('0.000', app(\App\Services\HarvestAvailabilityService::class)->availableQuantity($harvest));

        $this->post(route('plantation.harvests.post', [$entity, $harvest]))->assertRedirect();
        $harvest->refresh();
        $this->assertTrue($harvest->isPosted());
        $this->assertSame('5000.000', app(\App\Services\HarvestAvailabilityService::class)->availableQuantity($harvest));
        Http::assertNothingSent();
    }

    public function test_harvest_rejects_foreign_block_and_zero_quantity(): void
    {
        [$entity, $plantation] = $this->location();
        $foreignBlock = PlantationBlock::factory()->create();

        $this->from(route('plantation.harvests.create', $entity))
            ->post(route('plantation.harvests.store', $entity), $this->harvestPayload($plantation, $foreignBlock, [
                'quantity' => 10,
            ]))
            ->assertSessionHasErrors('plantation_block_public_id');

        $this->from(route('plantation.harvests.create', $entity))
            ->post(route('plantation.harvests.store', $entity), $this->harvestPayload($plantation, null, [
                'quantity' => 0,
            ]))
            ->assertSessionHasErrors('quantity');
    }

    public function test_sale_posts_reduces_availability_and_rejects_over_sell(): void
    {
        [$entity, $plantation, $block] = $this->location();
        $buyer = $this->buyer($entity);
        $harvest = $this->postedHarvest($entity, $plantation, $block, 5000);

        $this->post(route('plantation.harvest-sales.store', $entity), $this->salePayload($buyer, $harvest, 2000, 1500))->assertRedirect();
        $sale = HarvestSale::query()->forEntity($entity)->first();
        $this->assertSame('5000.000', app(\App\Services\HarvestAvailabilityService::class)->availableQuantity($harvest->fresh()));

        $this->post(route('plantation.harvest-sales.post', [$entity, $sale]))->assertRedirect();
        $this->assertSame('3000.000', app(\App\Services\HarvestAvailabilityService::class)->availableQuantity($harvest->fresh()));
        $this->assertSame(SalePaymentStatus::UNPAID, $sale->fresh()->payment_status);
        $this->assertDatabaseCount('budget_realizations', 0);

        $this->post(route('plantation.harvest-sales.store', $entity), $this->salePayload($buyer, $harvest, 4000, 1500));
        $over = HarvestSale::query()->forEntity($entity)->latest('id')->first();
        $this->from(route('plantation.harvest-sales.show', [$entity, $over]))
            ->post(route('plantation.harvest-sales.post', [$entity, $over]))
            ->assertSessionHas('error');
        $this->assertTrue($over->fresh()->isDraft());
        $this->assertSame('3000.000', app(\App\Services\HarvestAvailabilityService::class)->availableQuantity($harvest->fresh()));
    }

    public function test_concurrent_style_posts_cannot_oversell(): void
    {
        [$entity, $plantation, $block] = $this->location();
        $buyer = $this->buyer($entity);
        $harvest = $this->postedHarvest($entity, $plantation, $block, 5000);

        $this->post(route('plantation.harvest-sales.store', $entity), $this->salePayload($buyer, $harvest, 4000, 1000));
        $this->post(route('plantation.harvest-sales.store', $entity), $this->salePayload($buyer, $harvest, 3000, 1000));
        $first = HarvestSale::query()->forEntity($entity)->orderBy('id')->first();
        $second = HarvestSale::query()->forEntity($entity)->orderByDesc('id')->first();

        $this->post(route('plantation.harvest-sales.post', [$entity, $first]))->assertRedirect();
        $this->from(route('plantation.harvest-sales.show', [$entity, $second]))
            ->post(route('plantation.harvest-sales.post', [$entity, $second]))
            ->assertSessionHas('error');

        $this->assertSame('1000.000', app(\App\Services\HarvestAvailabilityService::class)->availableQuantity($harvest->fresh()));
    }

    public function test_duplicate_sale_post_is_idempotent(): void
    {
        [$entity, $plantation, $block] = $this->location();
        $buyer = $this->buyer($entity);
        $harvest = $this->postedHarvest($entity, $plantation, $block, 1000);
        $this->post(route('plantation.harvest-sales.store', $entity), $this->salePayload($buyer, $harvest, 200, 1000));
        $sale = HarvestSale::query()->first();
        $this->post(route('plantation.harvest-sales.post', [$entity, $sale]));
        $this->post(route('plantation.harvest-sales.post', [$entity, $sale]));
        $this->assertSame(1, $sale->items()->count());
        $this->assertSame('800.000', app(\App\Services\HarvestAvailabilityService::class)->availableQuantity($harvest->fresh()));
    }

    public function test_cancel_posted_sale_restores_availability_when_unpaid(): void
    {
        [$entity, $plantation, $block] = $this->location();
        $buyer = $this->buyer($entity);
        $harvest = $this->postedHarvest($entity, $plantation, $block, 1000);
        $this->post(route('plantation.harvest-sales.store', $entity), $this->salePayload($buyer, $harvest, 400, 2000));
        $sale = HarvestSale::query()->first();
        $this->post(route('plantation.harvest-sales.post', [$entity, $sale]));
        $this->post(route('plantation.harvest-sales.cancel', [$entity, $sale]), ['reason' => 'Batal kontrak']);
        $this->assertTrue($sale->fresh()->isCancelled());
        $this->assertSame('1000.000', app(\App\Services\HarvestAvailabilityService::class)->availableQuantity($harvest->fresh()));
    }

    public function test_cannot_cancel_harvest_or_sale_with_downstream_money(): void
    {
        [$entity, $plantation, $block] = $this->location();
        $buyer = $this->buyer($entity);
        $harvest = $this->postedHarvest($entity, $plantation, $block, 1000);
        $this->post(route('plantation.harvest-sales.store', $entity), $this->salePayload($buyer, $harvest, 400, 2000, ['adjustment_amount' => 0]));
        $sale = HarvestSale::query()->first();
        $this->post(route('plantation.harvest-sales.post', [$entity, $sale]));

        $this->from(route('plantation.harvests.show', [$entity, $harvest]))
            ->post(route('plantation.harvests.cancel', [$entity, $harvest]), ['reason' => 'Salah'])
            ->assertSessionHas('error');

        $this->post(route('plantation.harvest-sales.payments.store', [$entity, $sale]), [
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH->value,
        ]);
        $this->from(route('plantation.harvest-sales.show', [$entity, $sale]))
            ->post(route('plantation.harvest-sales.cancel', [$entity, $sale]), ['reason' => 'Batal'])
            ->assertSessionHas('error');
    }

    public function test_payments_outstanding_and_reverse(): void
    {
        [$entity, $plantation, $block] = $this->location();
        $buyer = $this->buyer($entity);
        $harvest = $this->postedHarvest($entity, $plantation, $block, 10);
        $this->post(route('plantation.harvest-sales.store', $entity), $this->salePayload($buyer, $harvest, 10, 1000));
        $sale = HarvestSale::query()->first();
        $this->post(route('plantation.harvest-sales.post', [$entity, $sale]));

        $this->from(route('plantation.harvest-sales.show', [$entity, $sale]))
            ->post(route('plantation.harvest-sales.payments.store', [$entity, $sale]), [
                'amount' => 20000,
                'payment_date' => now()->toDateString(),
                'payment_method' => PaymentMethod::CASH->value,
            ])
            ->assertSessionHas('error');

        $this->post(route('plantation.harvest-sales.payments.store', [$entity, $sale]), [
            'amount' => 4000,
            'payment_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::BANK_TRANSFER->value,
            'reference_number' => 'TRX-1',
        ])->assertRedirect();

        $sale->refresh();
        $this->assertSame(SalePaymentStatus::PARTIAL, $sale->payment_status);
        $this->assertSame('4000.00', $sale->paidAmount());
        $this->assertSame('6000.00', $sale->outstandingAmount());

        $this->post(route('plantation.harvest-sales.payments.store', [$entity, $sale]), [
            'amount' => 6000,
            'payment_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH->value,
        ]);
        $this->assertSame(SalePaymentStatus::PAID, $sale->fresh()->payment_status);
        $this->assertSame('0.00', $sale->fresh()->outstandingAmount());

        $payment = HarvestSalePayment::query()->first();
        $this->post(route('plantation.harvest-sales.payments.reverse', [$entity, $sale, $payment]), [
            'reason' => 'Salah catat',
        ]);
        $this->assertSame(PaymentRecordStatus::REVERSED, $payment->fresh()->status);
        $this->assertSame(SalePaymentStatus::PARTIAL, $sale->fresh()->payment_status);
        $this->assertSame(2, HarvestSalePayment::query()->count());
    }

    public function test_inactive_and_foreign_buyer_rejected(): void
    {
        [$entity, $plantation, $block] = $this->location();
        $harvest = $this->postedHarvest($entity, $plantation, $block, 10);
        $inactive = $this->buyer($entity);
        $inactive->update(['is_active' => false]);
        $foreignEntity = PlantationEntity::factory()->create();
        $foreign = Buyer::query()->create([
            'plantation_entity_id' => $foreignEntity->id,
            'name' => 'Pembeli Asing',
            'is_active' => true,
        ]);
        $this->grantAccess($entity);

        $this->from(route('plantation.harvest-sales.create', $entity))
            ->post(route('plantation.harvest-sales.store', $entity), $this->salePayload($inactive, $harvest, 1, 100))
            ->assertSessionHasErrors('buyer_public_id');

        $this->from(route('plantation.harvest-sales.create', $entity))
            ->post(route('plantation.harvest-sales.store', $entity), $this->salePayload($foreign, $harvest, 1, 100))
            ->assertSessionHasErrors('buyer_public_id');
    }

    public function test_entity_isolation_for_harvest_resources(): void
    {
        [$entityA, $plantationA, $blockA] = $this->location();
        $harvestA = $this->postedHarvest($entityA, $plantationA, $blockA, 10);
        $buyerA = $this->buyer($entityA);

        $entityB = PlantationEntity::factory()->create();
        $this->grantAccess($entityB);
        $plantationB = Plantation::factory()->create(['plantation_entity_id' => $entityB->id]);
        $blockB = PlantationBlock::factory()->create(['plantation_id' => $plantationB->id]);
        $harvestB = $this->postedHarvest($entityB, $plantationB, $blockB, 10);
        $buyerB = $this->buyer($entityB);
        $this->post(route('plantation.harvest-sales.store', $entityB), $this->salePayload($buyerB, $harvestB, 2, 100));
        $saleB = HarvestSale::query()->forEntity($entityB)->first();

        $this->grantAccess($entityA);
        $this->get(route('plantation.harvests.show', [$entityA, $harvestB]))->assertNotFound();
        $this->get(route('plantation.buyers.show', [$entityA, $buyerB]))->assertNotFound();
        $this->get(route('plantation.harvest-sales.show', [$entityA, $saleB]))->assertNotFound();
        $this->post(route('plantation.harvest-sales.store', $entityA), $this->salePayload($buyerB, $harvestA, 1, 100))
            ->assertSessionHasErrors('buyer_public_id');
        $this->post(route('plantation.harvest-sales.store', $entityA), $this->salePayload($buyerA, $harvestB, 1, 100))
            ->assertSessionHasErrors('items.0.harvest_public_id');
    }

    public function test_dashboard_and_reports_are_scoped(): void
    {
        [$entityA, $plantationA, $blockA] = $this->location();
        $this->postedHarvest($entityA, $plantationA, $blockA, 100, ['commodity' => Commodity::PEPPER->value, 'unit' => 'kg']);

        $entityB = PlantationEntity::factory()->create();
        $this->grantAccess($entityB);
        $plantationB = Plantation::factory()->create(['plantation_entity_id' => $entityB->id]);
        $this->postedHarvest($entityB, $plantationB, null, 999, ['commodity' => Commodity::RUBBER->value, 'unit' => 'kg']);

        $this->grantAccess($entityA);
        $this->get(route('plantation.dashboard', $entityA))
            ->assertOk()
            ->assertSee('Lada');

        $this->get(route('plantation.production-reports.show', $entityA))
            ->assertOk()
            ->assertSee('Lada')
            ->assertViewHas('summary', function (array $summary) {
                return collect($summary['groups'])->every(fn (array $group) => $group['commodity'] === Commodity::PEPPER->value);
            });

        $this->get(route('plantation.blocks.show', [$entityA, $blockA]))
            ->assertOk()
            ->assertSee('Lada');
    }

    public function test_buyer_with_history_is_deactivated(): void
    {
        [$entity, $plantation, $block] = $this->location();
        $buyer = $this->buyer($entity);
        $harvest = $this->postedHarvest($entity, $plantation, $block, 5);
        $this->post(route('plantation.harvest-sales.store', $entity), $this->salePayload($buyer, $harvest, 1, 100));
        $this->delete(route('plantation.buyers.destroy', [$entity, $buyer]));
        $this->assertFalse($buyer->fresh()->is_active);
        $this->assertDatabaseHas('buyers', ['id' => $buyer->id]);
    }

    /**
     * @return array{0: PlantationEntity, 1: Plantation, 2: PlantationBlock}
     */
    private function location(): array
    {
        $entity = PlantationEntity::factory()->create();
        $this->grantAccess($entity);
        $plantation = Plantation::factory()->create(['plantation_entity_id' => $entity->id]);
        $block = PlantationBlock::factory()->create(['plantation_id' => $plantation->id, 'area' => 2, 'crop_type' => 'Sawit']);

        return [$entity, $plantation, $block];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function buyer(PlantationEntity $entity, array $overrides = []): Buyer
    {
        $this->grantAccess($entity);
        $this->post(route('plantation.buyers.store', $entity), array_merge([
            'name' => 'PT Pembeli Tes',
            'is_active' => 1,
        ], $overrides))->assertRedirect();

        return Buyer::query()->forEntity($entity)->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function postedHarvest(PlantationEntity $entity, Plantation $plantation, ?PlantationBlock $block, int|string $qty, array $overrides = []): Harvest
    {
        $this->grantAccess($entity);
        $this->post(route('plantation.harvests.store', $entity), $this->harvestPayload($plantation, $block, array_merge([
            'quantity' => $qty,
        ], $overrides)))->assertRedirect();
        $harvest = Harvest::query()->forEntity($entity)->latest('id')->firstOrFail();
        $this->post(route('plantation.harvests.post', [$entity, $harvest]));

        return $harvest->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function harvestPayload(Plantation $plantation, ?PlantationBlock $block, array $overrides = []): array
    {
        return array_merge([
            'plantation_public_id' => $plantation->public_id,
            'plantation_block_public_id' => $block?->public_id,
            'harvest_date' => now()->toDateString(),
            'commodity' => Commodity::PALM_OIL_FFB->value,
            'quantity' => 1000,
            'unit' => 'kg',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function salePayload(Buyer $buyer, Harvest $harvest, int|string $qty, int|string $price, array $overrides = []): array
    {
        return array_merge([
            'buyer_public_id' => $buyer->public_id,
            'sale_date' => now()->toDateString(),
            'adjustment_amount' => 0,
            'items' => [[
                'harvest_public_id' => $harvest->public_id,
                'quantity' => $qty,
                'unit_price' => $price,
            ]],
        ], $overrides);
    }
}
