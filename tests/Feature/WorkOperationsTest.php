<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\BudgetItemCategory;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PayrollRateType;
use App\Enums\PayrollStatus;
use App\Enums\RealizationSourceType;
use App\Enums\RealizationStatus;
use App\Enums\WorkActivityStatus;
use App\Models\BudgetAllocationItem;
use App\Models\BudgetRealization;
use App\Models\FinanceBudgetAllocation;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Models\WorkActivity;
use App\Models\WorkAttendance;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use App\Models\WorkType;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkOperationsTest extends TestCase
{
    public function test_entity_can_crud_own_activity(): void
    {
        [$entity, $plantation, $workType] = $this->setupUnit();

        $this->post(route('plantation.work-activities.store', $entity), [
            'plantation_public_id' => $plantation->public_id,
            'work_type_public_id' => $workType->public_id,
            'activity_date' => '2026-08-20',
            'title' => 'Pemupukan',
            'status' => WorkActivityStatus::OPEN->value,
        ])->assertRedirect();

        $activity = WorkActivity::query()->forEntity($entity)->first();
        $this->assertNotNull($activity);
        $this->assertNotEmpty($activity->public_id);

        $this->get(route('plantation.work-activities.show', [$entity, $activity]))->assertOk()->assertSee('Pemupukan');

        $this->put(route('plantation.work-activities.update', [$entity, $activity]), [
            'plantation_public_id' => $plantation->public_id,
            'work_type_public_id' => $workType->public_id,
            'activity_date' => '2026-08-21',
            'title' => 'Pemupukan revisi',
            'status' => WorkActivityStatus::OPEN->value,
        ])->assertRedirect();

        $this->assertSame('Pemupukan revisi', $activity->fresh()->title);

        $this->delete(route('plantation.work-activities.destroy', [$entity, $activity]))
            ->assertRedirect(route('plantation.work-activities.index', $entity));

        $this->assertDatabaseMissing('work_activities', ['id' => $activity->id]);
    }

    public function test_cannot_open_other_entity_activity(): void
    {
        [$entityA] = $this->setupUnit();
        [, $plantationB, $workTypeB] = $this->setupUnit(grantAccess: false);
        $activityB = $this->activity($plantationB->plantationEntity, $plantationB, $workTypeB);

        $this->get(route('plantation.work-activities.show', [$entityA, $activityB]))->assertNotFound();
        $this->get(route('plantation.work-activities.edit', [$entityA, $activityB]))->assertNotFound();
    }

    public function test_block_must_belong_to_selected_plantation(): void
    {
        [$entity, $plantation, $workType] = $this->setupUnit();
        $otherPlantation = Plantation::factory()->create(['plantation_entity_id' => $entity->id]);
        $block = PlantationBlock::factory()->create(['plantation_id' => $otherPlantation->id]);

        $this->from(route('plantation.work-activities.create', $entity))
            ->post(route('plantation.work-activities.store', $entity), [
                'plantation_public_id' => $plantation->public_id,
                'plantation_block_public_id' => $block->public_id,
                'work_type_public_id' => $workType->public_id,
                'activity_date' => '2026-08-20',
                'title' => 'Salah blok',
            ])->assertSessionHasErrors('plantation_block_public_id');
    }

    public function test_work_type_must_belong_to_entity(): void
    {
        [$entity, $plantation] = $this->setupUnit();
        $foreignType = WorkType::factory()->create();

        $this->from(route('plantation.work-activities.create', $entity))
            ->post(route('plantation.work-activities.store', $entity), [
                'plantation_public_id' => $plantation->public_id,
                'work_type_public_id' => $foreignType->public_id,
                'activity_date' => '2026-08-20',
                'title' => 'Salah jenis',
            ])->assertSessionHasErrors('work_type_public_id');
    }

    public function test_invalid_status_is_rejected(): void
    {
        [$entity, $plantation, $workType] = $this->setupUnit();

        $this->from(route('plantation.work-activities.create', $entity))
            ->post(route('plantation.work-activities.store', $entity), [
                'plantation_public_id' => $plantation->public_id,
                'work_type_public_id' => $workType->public_id,
                'activity_date' => '2026-08-20',
                'title' => 'Status salah',
                'status' => 'UNKNOWN',
            ])->assertSessionHasErrors('status');
    }

    public function test_cancelled_activity_cannot_generate_payroll(): void
    {
        [$entity, $plantation, $workType] = $this->setupUnit();
        $activity = $this->activity($entity, $plantation, $workType, ['status' => WorkActivityStatus::CANCELLED]);
        $worker = Worker::factory()->create(['plantation_entity_id' => $entity->id, 'daily_rate' => 100000]);
        $attendance = $this->presentAttendance($activity, $worker);

        $this->from(route('plantation.work-activities.show', [$entity, $activity]))
            ->post(route('plantation.work-activities.payrolls.generate', [$entity, $activity]), [
                'attendance_public_ids' => [$attendance->public_id],
                'rate_type' => PayrollRateType::DAILY->value,
            ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('worker_payrolls', 0);
    }

    public function test_can_assign_worker_and_rejects_foreign_duplicate_inactive(): void
    {
        [$entity, $plantation, $workType] = $this->setupUnit();
        $activity = $this->activity($entity, $plantation, $workType);
        $worker = Worker::factory()->create(['plantation_entity_id' => $entity->id, 'daily_rate' => 120000]);
        $foreign = Worker::factory()->create();
        $inactive = Worker::factory()->create(['plantation_entity_id' => $entity->id, 'is_active' => false]);

        $this->post(route('plantation.work-activities.attendances.store', [$entity, $activity]), [
            'worker_public_ids' => [$worker->public_id],
        ])->assertRedirect()->assertSessionHas('success');

        $this->from(route('plantation.work-activities.show', [$entity, $activity]))
            ->post(route('plantation.work-activities.attendances.store', [$entity, $activity]), [
                'worker_public_ids' => [$worker->public_id],
            ])->assertRedirect()->assertSessionHas('error');

        $this->post(route('plantation.work-activities.attendances.store', [$entity, $activity]), [
            'worker_public_ids' => [$foreign->public_id],
        ])->assertSessionHasErrors('worker_public_ids.0');

        $this->from(route('plantation.work-activities.show', [$entity, $activity]))
            ->post(route('plantation.work-activities.attendances.store', [$entity, $activity]), [
                'worker_public_ids' => [$inactive->public_id],
            ])->assertRedirect()->assertSessionHas('error');
    }

    public function test_check_out_before_check_in_and_negative_units_rejected(): void
    {
        [$entity, $plantation, $workType] = $this->setupUnit();
        $activity = $this->activity($entity, $plantation, $workType);
        $worker = Worker::factory()->create(['plantation_entity_id' => $entity->id]);
        $attendance = $this->presentAttendance($activity, $worker);

        $this->from(route('plantation.work-activities.show', [$entity, $activity]))
            ->put(route('plantation.work-activities.attendances.update', [$entity, $activity, $attendance]), [
                'attendance_status' => AttendanceStatus::PRESENT->value,
                'check_in' => '2026-08-20 10:00:00',
                'check_out' => '2026-08-20 08:00:00',
            ])->assertSessionHasErrors('check_out');

        $this->from(route('plantation.work-activities.show', [$entity, $activity]))
            ->put(route('plantation.work-activities.attendances.update', [$entity, $activity, $attendance]), [
                'attendance_status' => AttendanceStatus::PRESENT->value,
                'work_units' => -1,
            ])->assertSessionHasErrors('work_units');
    }

    public function test_payroll_draft_from_present_snapshot_and_not_from_absent(): void
    {
        [$entity, $plantation, $workType] = $this->setupUnit();
        $workType->update(['default_rate' => 50000]);
        $activity = $this->activity($entity, $plantation, $workType);
        $present = Worker::factory()->create(['plantation_entity_id' => $entity->id, 'daily_rate' => 150000]);
        $absent = Worker::factory()->create(['plantation_entity_id' => $entity->id, 'daily_rate' => 150000]);
        $presentRow = $this->presentAttendance($activity, $present);
        $absentRow = WorkAttendance::query()->create([
            'work_activity_id' => $activity->id,
            'worker_id' => $absent->id,
            'attendance_status' => AttendanceStatus::ABSENT,
        ]);

        $this->post(route('plantation.work-activities.payrolls.generate', [$entity, $activity]), [
            'attendance_public_ids' => [$presentRow->public_id],
            'rate_type' => PayrollRateType::DAILY->value,
        ])->assertRedirect()->assertSessionHas('success');

        $payroll = WorkerPayroll::query()->first();
        $this->assertSame(PayrollStatus::DRAFT, $payroll->payroll_status);
        $this->assertSame('150000.00', (string) $payroll->rate_amount);
        $this->assertSame('150000.00', (string) $payroll->gross_amount);

        $present->update(['daily_rate' => 1]);
        $this->assertSame('150000.00', (string) $payroll->fresh()->rate_amount);

        $this->from(route('plantation.work-activities.show', [$entity, $activity]))
            ->post(route('plantation.work-activities.payrolls.generate', [$entity, $activity]), [
                'attendance_public_ids' => [$absentRow->public_id],
                'rate_type' => PayrollRateType::DAILY->value,
            ])->assertRedirect()->assertSessionHas('error');
    }

    public function test_unit_rate_and_adjustments(): void
    {
        [$entity, $plantation, $workType] = $this->setupUnit();
        $activity = $this->activity($entity, $plantation, $workType);
        $worker = Worker::factory()->create(['plantation_entity_id' => $entity->id, 'daily_rate' => 1000]);
        $attendance = $this->presentAttendance($activity, $worker, ['work_units' => 10]);

        $this->post(route('plantation.work-activities.payrolls.generate', [$entity, $activity]), [
            'attendance_public_ids' => [$attendance->public_id],
            'rate_type' => PayrollRateType::UNIT->value,
            'rate_amount' => 1000,
        ])->assertRedirect();

        $payroll = WorkerPayroll::query()->first();
        $this->assertSame('10000.00', (string) $payroll->gross_amount);

        $this->put(route('plantation.work-activities.payrolls.update', [$entity, $activity, $payroll]), [
            'rate_type' => PayrollRateType::UNIT->value,
            'rate_amount' => 1000,
            'work_quantity' => 10,
            'adjustment_amount' => 500,
        ])->assertRedirect();
        $this->assertSame('10500.00', (string) $payroll->fresh()->final_amount);

        $this->put(route('plantation.work-activities.payrolls.update', [$entity, $activity, $payroll]), [
            'rate_type' => PayrollRateType::UNIT->value,
            'rate_amount' => 1000,
            'work_quantity' => 10,
            'adjustment_amount' => -500,
        ])->assertRedirect();
        $this->assertSame('9500.00', (string) $payroll->fresh()->final_amount);

        $this->from(route('plantation.work-activities.show', [$entity, $activity]))
            ->put(route('plantation.work-activities.payrolls.update', [$entity, $activity, $payroll]), [
                'rate_type' => PayrollRateType::UNIT->value,
                'rate_amount' => 1000,
                'work_quantity' => 10,
                'adjustment_amount' => -20000,
            ])->assertRedirect()->assertSessionHas('error');
    }

    public function test_attendance_cannot_produce_duplicate_payroll_and_posted_is_locked(): void
    {
        [$entity, $plantation, $workType] = $this->setupUnit();
        $activity = $this->activity($entity, $plantation, $workType);
        $worker = Worker::factory()->create(['plantation_entity_id' => $entity->id, 'daily_rate' => 100000]);
        $attendance = $this->presentAttendance($activity, $worker);
        $item = $this->wageItem($entity, 500000);

        $this->post(route('plantation.work-activities.payrolls.generate', [$entity, $activity]), [
            'attendance_public_ids' => [$attendance->public_id],
            'rate_type' => PayrollRateType::DAILY->value,
        ]);
        $this->post(route('plantation.work-activities.payrolls.generate', [$entity, $activity]), [
            'attendance_public_ids' => [$attendance->public_id],
            'rate_type' => PayrollRateType::DAILY->value,
        ]);
        $this->assertDatabaseCount('worker_payrolls', 1);

        $payroll = WorkerPayroll::query()->first();
        $this->post(route('plantation.work-activities.payrolls.post', [$entity, $activity, $payroll]), [
            'budget_allocation_item_public_id' => $item->public_id,
        ])->assertRedirect();

        $this->from(route('plantation.work-activities.show', [$entity, $activity]))
            ->put(route('plantation.work-activities.payrolls.update', [$entity, $activity, $payroll]), [
                'rate_type' => PayrollRateType::DAILY->value,
                'rate_amount' => 1,
            ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame('100000.00', (string) $payroll->fresh()->rate_amount);
        $this->assertSame(PayrollStatus::POSTED, $payroll->fresh()->payroll_status);
    }

    public function test_posting_creates_atomic_wage_realization_without_duplicates_or_overbudget(): void
    {
        Http::fake();
        [$entity, $plantation, $workType] = $this->setupUnit();
        $activity = $this->activity($entity, $plantation, $workType);
        $worker = Worker::factory()->create(['plantation_entity_id' => $entity->id, 'daily_rate' => 100000]);
        $attendance = $this->presentAttendance($activity, $worker);
        $item = $this->wageItem($entity, 100000);

        $this->post(route('plantation.work-activities.payrolls.generate', [$entity, $activity]), [
            'attendance_public_ids' => [$attendance->public_id],
            'rate_type' => PayrollRateType::DAILY->value,
        ]);
        $payroll = WorkerPayroll::query()->first();

        $this->post(route('plantation.work-activities.payrolls.post', [$entity, $activity, $payroll]), [
            'budget_allocation_item_public_id' => $item->public_id,
        ])->assertRedirect()->assertSessionHas('success');

        $payroll->refresh();
        $this->assertSame(PayrollStatus::POSTED, $payroll->payroll_status);
        $this->assertNotNull($payroll->budget_realization_id);

        $realization = BudgetRealization::query()->find($payroll->budget_realization_id);
        $this->assertSame(RealizationSourceType::WORKER_PAYROLL, $realization->source_type);
        $this->assertSame($payroll->public_id, $realization->source_public_id);
        $this->assertSame('100000.00', (string) $realization->amount);
        $this->assertSame(RealizationStatus::ACTIVE, $realization->status);

        $this->post(route('plantation.work-activities.payrolls.post', [$entity, $activity, $payroll]), [
            'budget_allocation_item_public_id' => $item->public_id,
        ])->assertRedirect();
        $this->assertDatabaseCount('budget_realizations', 1);

        $otherWorker = Worker::factory()->create(['plantation_entity_id' => $entity->id, 'daily_rate' => 100000]);
        $otherAttendance = $this->presentAttendance($activity, $otherWorker);
        $this->post(route('plantation.work-activities.payrolls.generate', [$entity, $activity]), [
            'attendance_public_ids' => [$otherAttendance->public_id],
            'rate_type' => PayrollRateType::DAILY->value,
        ]);
        $otherPayroll = WorkerPayroll::query()->where('worker_id', $otherWorker->id)->first();
        $this->from(route('plantation.work-activities.show', [$entity, $activity]))
            ->post(route('plantation.work-activities.payrolls.post', [$entity, $activity, $otherPayroll]), [
                'budget_allocation_item_public_id' => $item->public_id,
            ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(PayrollStatus::DRAFT, $otherPayroll->fresh()->payroll_status);

        Http::assertNothingSent();
    }

    public function test_foreign_budget_item_and_payroll_url_are_rejected(): void
    {
        [$entityA, $plantationA, $workTypeA] = $this->setupUnit();
        [$entityB, $plantationB, $workTypeB] = $this->setupUnit(grantAccess: false);
        $this->grantAccess($entityA);

        $activityA = $this->activity($entityA, $plantationA, $workTypeA);
        $activityB = $this->activity($entityB, $plantationB, $workTypeB);
        $workerA = Worker::factory()->create(['plantation_entity_id' => $entityA->id, 'daily_rate' => 100000]);
        $workerB = Worker::factory()->create(['plantation_entity_id' => $entityB->id, 'daily_rate' => 100000]);
        $attendanceA = $this->presentAttendance($activityA, $workerA);
        $this->presentAttendance($activityB, $workerB);
        $itemB = $this->wageItem($entityB, 5_000_000);

        $this->post(route('plantation.work-activities.payrolls.generate', [$entityA, $activityA]), [
            'attendance_public_ids' => [$attendanceA->public_id],
            'rate_type' => PayrollRateType::DAILY->value,
        ]);
        $payrollA = WorkerPayroll::query()->first();
        $payrollB = WorkerPayroll::query()->create([
            'plantation_entity_id' => $entityB->id,
            'work_activity_id' => $activityB->id,
            'work_attendance_id' => WorkAttendance::query()->where('worker_id', $workerB->id)->value('id'),
            'worker_id' => $workerB->id,
            'work_type_id' => $workTypeB->id,
            'rate_type' => PayrollRateType::DAILY,
            'rate_amount' => 100000,
            'gross_amount' => 100000,
            'adjustment_amount' => 0,
            'final_amount' => 100000,
            'payroll_status' => PayrollStatus::DRAFT,
            'payment_status' => PaymentStatus::UNPAID,
        ]);

        $this->from(route('plantation.work-activities.show', [$entityA, $activityA]))
            ->post(route('plantation.work-activities.payrolls.post', [$entityA, $activityA, $payrollA]), [
                'budget_allocation_item_public_id' => $itemB->public_id,
            ])->assertRedirect()->assertSessionHas('error');

        $this->put(route('plantation.work-activities.payrolls.update', [$entityA, $activityA, $payrollB]), [
            'rate_type' => PayrollRateType::DAILY->value,
            'rate_amount' => 1,
        ])->assertNotFound();
    }

    public function test_mark_paid_cash_does_not_create_second_realization_or_finance_http(): void
    {
        Http::fake();
        [$entity, $plantation, $workType] = $this->setupUnit();
        $activity = $this->activity($entity, $plantation, $workType);
        $worker = Worker::factory()->create(['plantation_entity_id' => $entity->id, 'daily_rate' => 80000]);
        $attendance = $this->presentAttendance($activity, $worker);
        $item = $this->wageItem($entity, 80000);

        $this->post(route('plantation.work-activities.payrolls.generate', [$entity, $activity]), [
            'attendance_public_ids' => [$attendance->public_id],
            'rate_type' => PayrollRateType::FIXED->value,
        ]);
        $payroll = WorkerPayroll::query()->first();
        $this->post(route('plantation.work-activities.payrolls.post', [$entity, $activity, $payroll]), [
            'budget_allocation_item_public_id' => $item->public_id,
        ]);

        $this->from(route('plantation.work-activities.show', [$entity, $activity]))
            ->post(route('plantation.work-activities.payrolls.pay', [$entity, $activity, $payroll]), [])
            ->assertSessionHasErrors('paid_at');

        $this->post(route('plantation.work-activities.payrolls.pay', [$entity, $activity, $payroll]), [
            'paid_at' => '2026-08-21 09:00:00',
            'payment_notes' => 'Kas mandor',
        ])->assertRedirect()->assertSessionHas('success');

        $payroll->refresh();
        $this->assertSame(PaymentStatus::PAID, $payroll->payment_status);
        $this->assertSame(PaymentMethod::CASH, $payroll->payment_method);
        $this->assertDatabaseCount('budget_realizations', 1);
        Http::assertNothingSent();
    }

    public function test_cancel_draft_and_posted_reverses_active_realization(): void
    {
        [$entity, $plantation, $workType] = $this->setupUnit();
        $activity = $this->activity($entity, $plantation, $workType);
        $worker = Worker::factory()->create(['plantation_entity_id' => $entity->id, 'daily_rate' => 70000]);
        $attendance = $this->presentAttendance($activity, $worker);
        $item = $this->wageItem($entity, 70000);

        $this->post(route('plantation.work-activities.payrolls.generate', [$entity, $activity]), [
            'attendance_public_ids' => [$attendance->public_id],
            'rate_type' => PayrollRateType::DAILY->value,
        ]);
        $payroll = WorkerPayroll::query()->first();
        $this->post(route('plantation.work-activities.payrolls.cancel', [$entity, $activity, $payroll]))
            ->assertRedirect();
        $this->assertSame(PayrollStatus::CANCELLED, $payroll->fresh()->payroll_status);

        $this->post(route('plantation.work-activities.payrolls.generate', [$entity, $activity]), [
            'attendance_public_ids' => [$attendance->public_id],
            'rate_type' => PayrollRateType::DAILY->value,
        ]);
        $payroll = $payroll->fresh();
        $this->assertSame(PayrollStatus::DRAFT, $payroll->payroll_status);

        $this->post(route('plantation.work-activities.payrolls.post', [$entity, $activity, $payroll]), [
            'budget_allocation_item_public_id' => $item->public_id,
        ]);
        $this->assertSame('0.00', $item->fresh()->remainingToRealize());

        $this->post(route('plantation.work-activities.payrolls.cancel', [$entity, $activity, $payroll]))
            ->assertRedirect();

        $this->assertSame(PayrollStatus::CANCELLED, $payroll->fresh()->payroll_status);
        $this->assertSame(RealizationStatus::REVERSED, BudgetRealization::query()->first()->status);
        $this->assertSame('70000.00', $item->fresh()->remainingToRealize());
        $this->assertSame('0.00', Money::normalize($item->fresh()->realizedTotal()));
    }

    public function test_cannot_hard_delete_activity_with_history(): void
    {
        [$entity, $plantation, $workType] = $this->setupUnit();
        $activity = $this->activity($entity, $plantation, $workType);
        $worker = Worker::factory()->create(['plantation_entity_id' => $entity->id]);
        $this->presentAttendance($activity, $worker);

        $this->from(route('plantation.work-activities.show', [$entity, $activity]))
            ->delete(route('plantation.work-activities.destroy', [$entity, $activity]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('work_activities', ['id' => $activity->id]);
    }

    public function test_dashboard_and_worker_history_are_scoped(): void
    {
        [$entityA, $plantationA, $workTypeA] = $this->setupUnit();
        [$entityB, $plantationB, $workTypeB] = $this->setupUnit(grantAccess: false);
        $this->grantAccess($entityA);

        $activityA = $this->activity($entityA, $plantationA, $workTypeA);
        $this->activity($entityB, $plantationB, $workTypeB);
        $workerA = Worker::factory()->create(['plantation_entity_id' => $entityA->id, 'daily_rate' => 90000]);
        $workerB = Worker::factory()->create(['plantation_entity_id' => $entityB->id, 'daily_rate' => 90000]);
        $this->presentAttendance($activityA, $workerA);
        $item = $this->wageItem($entityA, 90000);
        $this->post(route('plantation.work-activities.payrolls.generate', [$entityA, $activityA]), [
            'attendance_public_ids' => [WorkAttendance::query()->first()->public_id],
            'rate_type' => PayrollRateType::DAILY->value,
        ]);
        $payroll = WorkerPayroll::query()->first();
        $this->post(route('plantation.work-activities.payrolls.post', [$entityA, $activityA, $payroll]), [
            'budget_allocation_item_public_id' => $item->public_id,
        ]);

        $this->get(route('plantation.dashboard', $entityA))
            ->assertOk()
            ->assertViewHas('activityCountThisMonth', 1)
            ->assertViewHas('workerCount', 1);

        $this->get(route('plantation.workers.show', [$entityA, $workerA]))
            ->assertOk()
            ->assertSee($activityA->title);

        $this->get(route('plantation.workers.show', [$entityA, $workerB]))->assertNotFound();
    }

    /**
     * @return array{0: PlantationEntity, 1: Plantation, 2: WorkType}
     */
    private function setupUnit(bool $grantAccess = true): array
    {
        $entity = PlantationEntity::factory()->create();
        $plantation = Plantation::factory()->create(['plantation_entity_id' => $entity->id]);
        $workType = WorkType::factory()->create(['plantation_entity_id' => $entity->id, 'default_rate' => 75000]);

        if ($grantAccess) {
            $this->grantAccess($entity);
        }

        return [$entity, $plantation, $workType];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function activity(PlantationEntity $entity, Plantation $plantation, WorkType $workType, array $overrides = []): WorkActivity
    {
        return WorkActivity::factory()->create([
            'plantation_entity_id' => $entity->id,
            'plantation_id' => $plantation->id,
            'work_type_id' => $workType->id,
            'status' => WorkActivityStatus::OPEN,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function presentAttendance(WorkActivity $activity, Worker $worker, array $overrides = []): WorkAttendance
    {
        return WorkAttendance::query()->create([
            'work_activity_id' => $activity->id,
            'worker_id' => $worker->id,
            'attendance_status' => AttendanceStatus::PRESENT,
            ...$overrides,
        ]);
    }

    private function wageItem(PlantationEntity $entity, float $amount): BudgetAllocationItem
    {
        $allocation = FinanceBudgetAllocation::factory()->create([
            'plantation_entity_id' => $entity->id,
            'allocated_amount' => $amount,
            'status' => 'ACTIVE',
        ]);

        return $allocation->items()->create([
            'category' => BudgetItemCategory::WAGES,
            'name' => 'Upah',
            'allocated_amount' => $amount,
        ]);
    }
}
