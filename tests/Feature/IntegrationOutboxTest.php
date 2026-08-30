<?php

namespace Tests\Feature;

use App\Enums\IntegrationEventType;
use App\Enums\IntegrationOutboxStatus;
use App\Enums\InventoryCategory;
use App\Enums\PaymentMethod;
use App\Enums\PayrollRateType;
use App\Jobs\DispatchIntegrationOutboxEvent;
use App\Models\Harvest;
use App\Models\HarvestSale;
use App\Models\HarvestSalePayment;
use App\Models\IntegrationOutbox;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use App\Models\WorkType;
use App\Services\IntegrationOutboxService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IntegrationOutboxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.integration.events_enabled' => true]);
        Queue::fake();
    }

    public function test_purchase_post_creates_outbox_in_same_transaction(): void
    {
        [$entity, $purchase] = $this->postedPurchase();

        $this->assertDatabaseHas('integration_outbox', [
            'event_type' => IntegrationEventType::PLANTATION_PURCHASE_POSTED->value,
            'source_public_id' => $purchase->public_id,
            'plantation_entity_public_id' => $entity->public_id,
            'finance_entity_public_id' => $entity->finance_entity_public_id,
            'status' => IntegrationOutboxStatus::PENDING->value,
        ]);

        $row = IntegrationOutbox::query()->first();
        $this->assertNotNull($row->event_id);
        $this->assertSame(1, $row->event_version);
        $this->assertSame($purchase->public_id, $row->payload['purchase_public_id']);
        $this->assertArrayNotHasKey('id', $row->payload);
        $this->assertArrayNotHasKey('finance_entity_id', $row->payload);
        $this->assertArrayNotHasKey('plantation_entity_id', $row->payload);
        Queue::assertPushed(DispatchIntegrationOutboxEvent::class);
    }

    public function test_failed_purchase_does_not_create_outbox(): void
    {
        [$entity, $item] = $this->entityWithItem();
        $this->post(route('plantation.purchases.store', $entity), [
            'purchase_date' => now()->toDateString(),
            'adjustment_amount' => 0,
            'items' => [[
                'inventory_item_public_id' => $item->public_id,
                'quantity' => 10,
                'unit_cost' => 1000,
            ]],
        ]);
        $purchase = InventoryPurchase::query()->first();
        $purchase->update(['status' => 'CANCELLED']);

        $this->from(route('plantation.purchases.show', [$entity, $purchase]))
            ->post(route('plantation.purchases.post', [$entity, $purchase]))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('integration_outbox', 0);
    }

    public function test_duplicate_purchase_post_does_not_duplicate_event(): void
    {
        [, $purchase] = $this->postedPurchase();
        $entity = $purchase->plantationEntity;
        $this->post(route('plantation.purchases.post', [$entity, $purchase]));
        $this->assertDatabaseCount('integration_outbox', 1);
    }

    public function test_purchase_cancel_creates_reversal_event(): void
    {
        [$entity, $purchase] = $this->postedPurchase();
        $this->post(route('plantation.purchases.cancel', [$entity, $purchase]), ['reason' => 'Salah'])->assertRedirect();
        $this->assertDatabaseHas('integration_outbox', [
            'event_type' => IntegrationEventType::PLANTATION_PURCHASE_CANCELLED->value,
            'source_public_id' => $purchase->public_id,
        ]);
    }

    public function test_payroll_paid_creates_event_but_post_does_not(): void
    {
        [$entity, $activity, $payroll, $item] = $this->postedPayroll();
        $this->assertDatabaseCount('integration_outbox', 0);

        $this->post(route('plantation.work-activities.payrolls.pay', [$entity, $activity, $payroll]), [
            'paid_at' => '2026-08-21 09:00:00',
        ])->assertRedirect();

        $this->assertDatabaseHas('integration_outbox', [
            'event_type' => IntegrationEventType::PLANTATION_PAYROLL_PAID->value,
            'source_public_id' => $payroll->fresh()->public_id,
        ]);
        $this->assertSame(1, IntegrationOutbox::query()->count());
    }

    public function test_sale_posted_cancelled_payment_and_reverse_create_events(): void
    {
        [$entity, $sale] = $this->postedSale();
        $this->assertDatabaseHas('integration_outbox', [
            'event_type' => IntegrationEventType::HARVEST_SALE_POSTED->value,
            'source_public_id' => $sale->public_id,
        ]);

        $this->post(route('plantation.harvest-sales.payments.store', [$entity, $sale]), [
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH->value,
        ]);
        $payment = HarvestSalePayment::query()->first();
        $this->assertDatabaseHas('integration_outbox', [
            'event_type' => IntegrationEventType::HARVEST_SALE_PAYMENT_RECEIVED->value,
            'source_public_id' => $payment->public_id,
        ]);

        $this->post(route('plantation.harvest-sales.payments.reverse', [$entity, $sale, $payment]), [
            'reason' => 'Salah',
        ]);
        $this->assertDatabaseHas('integration_outbox', [
            'event_type' => IntegrationEventType::HARVEST_SALE_PAYMENT_REVERSED->value,
            'source_public_id' => $payment->public_id,
        ]);

        $this->post(route('plantation.harvest-sales.cancel', [$entity, $sale]), ['reason' => 'Batal']);
        $this->assertDatabaseHas('integration_outbox', [
            'event_type' => IntegrationEventType::HARVEST_SALE_CANCELLED->value,
            'source_public_id' => $sale->public_id,
        ]);
    }

    public function test_disabled_config_creates_no_events(): void
    {
        config(['services.integration.events_enabled' => false]);
        $this->postedPurchase();
        $this->assertDatabaseCount('integration_outbox', 0);
    }

    public function test_dispatch_success_marks_sent_and_is_idempotent(): void
    {
        Queue::fake();
        config([
            'services.finance.base_url' => 'http://finance.test',
            'services.finance.service_token' => 'testing-finance-service-token',
        ]);
        Http::fake([
            '*' => Http::response(['ok' => true, 'already_processed' => false], 200),
        ]);

        [$entity, $purchase] = $this->postedPurchase();
        $row = IntegrationOutbox::query()->first();
        app(IntegrationOutboxService::class)->process($row->id);

        $row->refresh();
        $this->assertSame(IntegrationOutboxStatus::SENT, $row->status);
        $this->assertNotNull($row->processed_at);
        $this->assertNull($row->last_error);

        Http::assertSent(fn ($request) => $request->url() === 'http://finance.test/api/internal/plantation/events'
            && $request->hasHeader('Authorization', 'Bearer testing-finance-service-token')
            && $request['event_id'] === $row->event_id
            && $request['event_type'] === IntegrationEventType::PLANTATION_PURCHASE_POSTED->value);

        app(IntegrationOutboxService::class)->process($row->id);
        Http::assertSentCount(1);
    }

    public function test_timeout_and_5xx_retry_while_422_is_permanent(): void
    {
        config([
            'services.finance.base_url' => 'http://finance.test',
            'services.finance.service_token' => 'testing-finance-service-token',
        ]);

        [$entity, $purchase] = $this->postedPurchase();
        $row = IntegrationOutbox::query()->first();

        Http::fakeSequence()
            ->push(['message' => 'down'], 503)
            ->push(['message' => 'bad'], 422);

        app(IntegrationOutboxService::class)->process($row->id);
        $row->refresh();
        $this->assertSame(IntegrationOutboxStatus::PENDING, $row->status);
        $this->assertSame(1, $row->attempts);
        $this->assertStringNotContainsString('testing-finance-service-token', (string) $row->last_error);

        $row->update(['available_at' => now()]);
        app(IntegrationOutboxService::class)->process($row->id);
        $this->assertSame(IntegrationOutboxStatus::FAILED, $row->fresh()->status);
    }

    public function test_dependency_409_is_retryable_and_manual_retry_works(): void
    {
        config([
            'services.finance.base_url' => 'http://finance.test',
            'services.finance.service_token' => 'testing-finance-service-token',
        ]);
        [$entity, $purchase] = $this->postedPurchase();
        $row = IntegrationOutbox::query()->first();

        Http::fake(['*' => Http::response([
            'ok' => false,
            'code' => 'DEPENDENCY_NOT_READY',
            'message' => 'not ready',
        ], 409)]);
        app(IntegrationOutboxService::class)->process($row->id);
        $this->assertSame(IntegrationOutboxStatus::PENDING, $row->fresh()->status);

        $row->update(['status' => IntegrationOutboxStatus::FAILED, 'available_at' => now()]);
        $this->grantAccess($entity);
        $this->post(route('plantation.integration.retry', [$entity, $row]))->assertRedirect();
        $this->assertSame(IntegrationOutboxStatus::PENDING, $row->fresh()->status);
    }

    /**
     * @return array{0: PlantationEntity, 1: InventoryPurchase}
     */
    private function postedPurchase(): array
    {
        $entity = PlantationEntity::factory()->create();
        $this->grantAccess($entity);
        $item = InventoryItem::factory()->create([
            'plantation_entity_id' => $entity->id,
            'category' => InventoryCategory::Fertilizer,
        ]);
        $this->post(route('plantation.purchases.store', $entity), [
            'purchase_date' => now()->toDateString(),
            'adjustment_amount' => 0,
            'items' => [[
                'inventory_item_public_id' => $item->public_id,
                'quantity' => 10,
                'unit_cost' => 1500,
            ]],
        ])->assertRedirect();
        $purchase = InventoryPurchase::query()->firstOrFail();
        $this->post(route('plantation.purchases.post', [$entity, $purchase]))->assertRedirect();

        return [$entity, $purchase->fresh()];
    }

    /**
     * @return array{0: PlantationEntity, 1: InventoryItem}
     */
    private function entityWithItem(): array
    {
        $entity = PlantationEntity::factory()->create();
        $this->grantAccess($entity);
        $item = InventoryItem::factory()->create([
            'plantation_entity_id' => $entity->id,
            'category' => InventoryCategory::Fertilizer,
        ]);

        return [$entity, $item];
    }

    /**
     * @return array{0: PlantationEntity, 1: \App\Models\WorkActivity, 2: WorkerPayroll, 3: \App\Models\BudgetAllocationItem}
     */
    private function postedPayroll(): array
    {
        $entity = PlantationEntity::factory()->create();
        $this->grantAccess($entity);
        $plantation = Plantation::factory()->create(['plantation_entity_id' => $entity->id]);
        $workType = WorkType::factory()->create(['plantation_entity_id' => $entity->id, 'default_rate' => 80000]);
        $this->post(route('plantation.work-activities.store', $entity), [
            'plantation_public_id' => $plantation->public_id,
            'work_type_public_id' => $workType->public_id,
            'activity_date' => now()->toDateString(),
            'title' => 'Panen uji',
            'status' => 'OPEN',
        ]);
        $activity = \App\Models\WorkActivity::query()->firstOrFail();
        $worker = Worker::factory()->create(['plantation_entity_id' => $entity->id, 'daily_rate' => 80000]);
        $this->post(route('plantation.work-activities.attendances.store', [$entity, $activity]), [
            'worker_public_ids' => [$worker->public_id],
            'attendance_status' => 'PRESENT',
        ]);
        $attendance = \App\Models\WorkAttendance::query()->firstOrFail();
        $allocation = \App\Models\FinanceBudgetAllocation::factory()->create([
            'plantation_entity_id' => $entity->id,
            'allocated_amount' => 5_000_000,
        ]);
        $this->post(route('plantation.budgets.items.store', [$entity, $allocation]), [
            'category' => 'WAGES',
            'name' => 'Upah',
            'allocated_amount' => 5_000_000,
        ]);
        $item = \App\Models\BudgetAllocationItem::query()->firstOrFail();
        $this->post(route('plantation.work-activities.payrolls.generate', [$entity, $activity]), [
            'attendance_public_ids' => [$attendance->public_id],
            'rate_type' => PayrollRateType::FIXED->value,
        ]);
        $payroll = WorkerPayroll::query()->firstOrFail();
        $this->post(route('plantation.work-activities.payrolls.post', [$entity, $activity, $payroll]), [
            'budget_allocation_item_public_id' => $item->public_id,
        ])->assertRedirect();

        return [$entity, $activity, $payroll->fresh(), $item];
    }

    /**
     * @return array{0: PlantationEntity, 1: HarvestSale}
     */
    private function postedSale(): array
    {
        $entity = PlantationEntity::factory()->create();
        $this->grantAccess($entity);
        $plantation = Plantation::factory()->create(['plantation_entity_id' => $entity->id]);
        $block = PlantationBlock::factory()->create(['plantation_id' => $plantation->id]);
        $this->post(route('plantation.harvests.store', $entity), [
            'plantation_public_id' => $plantation->public_id,
            'plantation_block_public_id' => $block->public_id,
            'harvest_date' => now()->toDateString(),
            'commodity' => 'PALM_OIL_FFB',
            'quantity' => 100,
            'unit' => 'kg',
        ]);
        $harvest = Harvest::query()->firstOrFail();
        $this->post(route('plantation.harvests.post', [$entity, $harvest]));
        $this->post(route('plantation.buyers.store', $entity), ['name' => 'PT Pembeli', 'is_active' => 1]);
        $buyer = \App\Models\Buyer::query()->firstOrFail();
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
        $this->post(route('plantation.harvest-sales.post', [$entity, $sale]))->assertRedirect();

        return [$entity, $sale->fresh()];
    }
}
