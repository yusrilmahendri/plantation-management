<?php

namespace Tests\Feature;

use App\Enums\ProductionDocumentStatus;
use App\Models\Buyer;
use App\Models\Harvest;
use App\Models\HarvestSale;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Enums\Commodity;
use Tests\TestCase;

class HarvestFinanceEventTest extends TestCase
{
    public function test_internal_api_lists_posted_sales_for_finance_pull(): void
    {
        $entity = PlantationEntity::factory()->create();
        $this->grantAccess($entity);
        $plantation = Plantation::factory()->create(['plantation_entity_id' => $entity->id]);
        $block = PlantationBlock::factory()->create(['plantation_id' => $plantation->id]);
        $this->post(route('plantation.harvests.store', $entity), [
            'plantation_public_id' => $plantation->public_id,
            'plantation_block_public_id' => $block->public_id,
            'harvest_date' => now()->toDateString(),
            'commodity' => Commodity::PALM_OIL_FFB->value,
            'quantity' => 100,
            'unit' => 'kg',
        ]);
        $harvest = Harvest::query()->firstOrFail();
        $this->post(route('plantation.harvests.post', [$entity, $harvest]));
        $this->post(route('plantation.buyers.store', $entity), ['name' => 'PT Pembeli Tes', 'is_active' => 1]);
        $buyer = Buyer::query()->firstOrFail();
        $this->post(route('plantation.harvest-sales.store', $entity), [
            'buyer_public_id' => $buyer->public_id,
            'sale_date' => now()->toDateString(),
            'adjustment_amount' => 0,
            'items' => [[
                'harvest_public_id' => $harvest->public_id,
                'quantity' => 100,
                'unit_price' => 2000,
            ]],
        ]);
        $sale = HarvestSale::query()->firstOrFail();
        $this->post(route('plantation.harvest-sales.post', [$entity, $sale]));

        $this->getJson('/api/internal/plantation-entities/'.$entity->public_id.'/harvest-sales')
            ->assertUnauthorized();

        $this->getJson(
            '/api/internal/plantation-entities/'.$entity->public_id.'/harvest-sales',
            $this->financeHeaders()
        )->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $sale->public_id)
            ->assertJsonPath('data.0.status', ProductionDocumentStatus::POSTED->value);
    }
}
