<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\BudgetItemCategory;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PayrollRateType;
use App\Enums\PayrollStatus;
use App\Enums\RealizationSourceType;
use App\Models\BudgetAllocationItem;
use App\Models\WorkActivity;
use App\Models\WorkAttendance;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use App\Models\WorkType;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayrollService
{
    public function __construct(
        private readonly BudgetAllocationService $budgets,
        private readonly IntegrationOutboxService $outbox,
    ) {}

    /**
     * @param  list<int>  $attendanceIds
     * @param  array{rate_type: string, rate_amount?: float|int|string|null, work_quantity?: float|int|string|null, adjustment_amount?: float|int|string|null, rates?: array<int, array{rate_amount?: mixed, work_quantity?: mixed, adjustment_amount?: mixed}>}  $defaults
     * @return Collection<int, WorkerPayroll>
     */
    public function generateDrafts(WorkActivity $activity, array $attendanceIds, array $defaults): Collection
    {
        if (! $activity->allowsPayrollGeneration()) {
            throw new InvalidArgumentException('Aktivitas yang dibatalkan tidak dapat menghasilkan payroll.');
        }

        $rateType = PayrollRateType::from($defaults['rate_type']);

        return DB::transaction(function () use ($activity, $attendanceIds, $defaults, $rateType) {
            $created = collect();

            foreach ($attendanceIds as $attendanceId) {
                $attendance = WorkAttendance::query()
                    ->where('work_activity_id', $activity->id)
                    ->whereKey($attendanceId)
                    ->lockForUpdate()
                    ->first();

                if (! $attendance instanceof WorkAttendance) {
                    throw new InvalidArgumentException('Absensi tidak valid untuk aktivitas ini.');
                }

                if ($attendance->attendance_status !== AttendanceStatus::PRESENT) {
                    throw new InvalidArgumentException('Payroll hanya dapat dibuat untuk pekerja yang hadir.');
                }

                $overrides = $defaults['rates'][$attendance->id] ?? [];
                $created->push($this->upsertDraft($activity, $attendance, $rateType, $defaults, $overrides));
            }

            return $created;
        });
    }

    /**
     * @param  array{rate_type?: string, rate_amount?: mixed, work_quantity?: mixed, adjustment_amount?: mixed}  $data
     */
    public function updateDraft(WorkerPayroll $payroll, array $data): WorkerPayroll
    {
        if (! $payroll->isDraft()) {
            throw new InvalidArgumentException('Hanya payroll draf yang dapat diubah.');
        }

        $rateType = isset($data['rate_type'])
            ? PayrollRateType::from($data['rate_type'])
            : $payroll->rate_type;

        $amounts = $this->calculate(
            $rateType,
            $data['rate_amount'] ?? $payroll->rate_amount,
            $data['work_quantity'] ?? $payroll->work_quantity,
            $data['adjustment_amount'] ?? $payroll->adjustment_amount,
        );

        $payroll->update([
            'rate_type' => $rateType,
            ...$amounts,
        ]);

        return $payroll->fresh() ?? $payroll;
    }

    public function post(WorkerPayroll $payroll, BudgetAllocationItem $item): WorkerPayroll
    {
        return DB::transaction(function () use ($payroll, $item) {
            $locked = WorkerPayroll::query()->whereKey($payroll->id)->lockForUpdate()->firstOrFail();

            if ($locked->payroll_status === PayrollStatus::CANCELLED) {
                throw new InvalidArgumentException('Payroll yang dibatalkan tidak dapat diposting.');
            }

            if ($locked->isPosted() && $locked->budget_realization_id) {
                return $locked;
            }

            $this->assertWageItem($locked, $item);

            $locked->loadMissing(['worker', 'activity']);

            $realization = $this->budgets->recordSourceRealization($item, [
                'source_type' => RealizationSourceType::WORKER_PAYROLL,
                'source_public_id' => $locked->public_id,
                'amount' => $locked->final_amount,
                'realization_date' => $locked->activity->activity_date->toDateString(),
                'description' => sprintf(
                    'Upah %s — %s',
                    $locked->worker?->name ?? 'pekerja',
                    $locked->activity->title,
                ),
            ]);

            $locked->update([
                'payroll_status' => PayrollStatus::POSTED,
                'budget_allocation_item_id' => $item->id,
                'budget_realization_id' => $realization->id,
            ]);

            return $locked->fresh() ?? $locked;
        });
    }

    public function cancel(WorkerPayroll $payroll, string $reason = 'Payroll dibatalkan'): WorkerPayroll
    {
        return DB::transaction(function () use ($payroll, $reason) {
            $locked = WorkerPayroll::query()->whereKey($payroll->id)->lockForUpdate()->firstOrFail();

            if ($locked->payroll_status === PayrollStatus::CANCELLED) {
                return $locked;
            }

            if ($locked->isPosted() && $locked->budget_realization_id) {
                $realization = $locked->budgetRealization;
                if ($realization) {
                    $this->budgets->reverseRealization($realization, $reason);
                }
            }

            $locked->update([
                'payroll_status' => PayrollStatus::CANCELLED,
            ]);

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * @param  array{paid_at: string, payment_notes?: string|null}  $data
     */
    public function markPaid(WorkerPayroll $payroll, array $data): WorkerPayroll
    {
        return DB::transaction(function () use ($payroll, $data) {
            $locked = WorkerPayroll::query()->whereKey($payroll->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPosted()) {
                throw new InvalidArgumentException('Hanya payroll yang sudah diposting yang dapat ditandai dibayar.');
            }

            if ($locked->payment_status === PaymentStatus::PAID) {
                return $locked;
            }

            $locked->update([
                'payment_status' => PaymentStatus::PAID,
                'payment_method' => PaymentMethod::CASH,
                'paid_at' => $data['paid_at'],
                'payment_notes' => $data['payment_notes'] ?? null,
            ]);

            $paid = $locked->fresh(['worker', 'activity', 'workType', 'plantationEntity']) ?? $locked;
            $this->outbox->recordPayrollPaid($paid);

            return $paid;
        });
    }

    /**
     * @return Collection<int, BudgetAllocationItem>
     */
    public function wageItemsFor(WorkActivity $activity): Collection
    {
        return BudgetAllocationItem::query()
            ->where('category', BudgetItemCategory::WAGES)
            ->whereHas('allocation', function ($query) use ($activity): void {
                $query->forEntity($activity->plantationEntity)
                    ->where('status', 'ACTIVE');
            })
            ->with('allocation')
            ->get()
            ->filter(fn (BudgetAllocationItem $item) => Money::cmp($item->remainingToRealize(), '0') === 1)
            ->values();
    }

    /**
     * @param  array{rate_amount?: mixed, work_quantity?: mixed, adjustment_amount?: mixed}  $defaults
     * @param  array{rate_amount?: mixed, work_quantity?: mixed, adjustment_amount?: mixed}  $overrides
     */
    private function upsertDraft(
        WorkActivity $activity,
        WorkAttendance $attendance,
        PayrollRateType $rateType,
        array $defaults,
        array $overrides,
    ): WorkerPayroll {
        $existing = WorkerPayroll::query()
            ->where('work_attendance_id', $attendance->id)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof WorkerPayroll && $existing->isPosted()) {
            throw new InvalidArgumentException('Absensi ini sudah memiliki payroll yang diposting.');
        }

        $attendance->loadMissing('worker');
        $worker = $attendance->worker;
        $workType = $activity->workType ?? WorkType::query()->findOrFail($activity->work_type_id);

        $rateAmount = $overrides['rate_amount'] ?? $defaults['rate_amount'] ?? $this->resolveRate($worker, $workType);
        if ($rateAmount === null || Money::cmp((string) $rateAmount, '0') !== 1) {
            throw new InvalidArgumentException(
                'Tarif untuk '.$worker->name.' wajib diisi karena tidak ada tarif default.'
            );
        }

        $quantity = $overrides['work_quantity']
            ?? $defaults['work_quantity']
            ?? $attendance->work_units;

        $adjustment = $overrides['adjustment_amount'] ?? $defaults['adjustment_amount'] ?? 0;
        $amounts = $this->calculate($rateType, $rateAmount, $quantity, $adjustment);

        $payload = [
            'plantation_entity_id' => $activity->plantation_entity_id,
            'work_activity_id' => $activity->id,
            'work_attendance_id' => $attendance->id,
            'worker_id' => $attendance->worker_id,
            'work_type_id' => $activity->work_type_id,
            'rate_type' => $rateType,
            'payroll_status' => PayrollStatus::DRAFT,
            'payment_status' => PaymentStatus::UNPAID,
            ...$amounts,
        ];

        if ($existing instanceof WorkerPayroll) {
            $existing->update($payload);

            return $existing->fresh() ?? $existing;
        }

        return WorkerPayroll::query()->create($payload);
    }

    private function calculate(
        PayrollRateType $rateType,
        mixed $rateAmount,
        mixed $quantity,
        mixed $adjustment,
    ): array {
        $rate = Money::normalize($rateAmount);
        $qty = $quantity === null || $quantity === '' ? null : Money::normalize($quantity);
        $adj = Money::normalize($adjustment ?? 0);

        $gross = match ($rateType) {
            PayrollRateType::UNIT => Money::mul($rate, $qty ?? '0'),
            PayrollRateType::DAILY, PayrollRateType::FIXED => $rate,
        };

        $final = Money::add($gross, $adj);

        if (Money::cmp($final, '0') === -1) {
            throw new InvalidArgumentException('Jumlah akhir upah tidak boleh negatif.');
        }

        return [
            'rate_amount' => $rate,
            'work_quantity' => $qty,
            'gross_amount' => $gross,
            'adjustment_amount' => $adj,
            'final_amount' => $final,
        ];
    }

    private function resolveRate(?Worker $worker, WorkType $workType): mixed
    {
        if ($worker && $worker->daily_rate !== null && Money::cmp($worker->daily_rate, '0') === 1) {
            return $worker->daily_rate;
        }

        if ($workType->default_rate !== null && Money::cmp($workType->default_rate, '0') === 1) {
            return $workType->default_rate;
        }

        return null;
    }

    private function assertWageItem(WorkerPayroll $payroll, BudgetAllocationItem $item): void
    {
        $item->loadMissing('allocation');

        if ($item->allocation === null
            || (int) $item->allocation->plantation_entity_id !== (int) $payroll->plantation_entity_id) {
            throw new InvalidArgumentException('Item anggaran tidak valid untuk unit ini.');
        }

        if ($item->category !== BudgetItemCategory::WAGES) {
            throw new InvalidArgumentException('Payroll harus memakai item anggaran kategori Upah.');
        }
    }
}
