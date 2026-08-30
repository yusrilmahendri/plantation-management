<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PayrollRateType;
use App\Enums\PayrollStatus;
use App\Models\Concerns\BelongsToPlantationEntity;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerPayroll extends Model
{
    use BelongsToPlantationEntity;
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'plantation_entity_id',
        'work_activity_id',
        'work_attendance_id',
        'worker_id',
        'work_type_id',
        'rate_type',
        'rate_amount',
        'work_quantity',
        'gross_amount',
        'adjustment_amount',
        'final_amount',
        'payroll_status',
        'payment_status',
        'payment_method',
        'paid_at',
        'payment_notes',
        'budget_allocation_item_id',
        'budget_realization_id',
    ];

    protected function casts(): array
    {
        return [
            'rate_type' => PayrollRateType::class,
            'rate_amount' => 'decimal:2',
            'work_quantity' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'payroll_status' => PayrollStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(WorkActivity::class, 'work_activity_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(WorkAttendance::class, 'work_attendance_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class);
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetAllocationItem::class, 'budget_allocation_item_id');
    }

    public function budgetRealization(): BelongsTo
    {
        return $this->belongsTo(BudgetRealization::class, 'budget_realization_id');
    }

    public function isPosted(): bool
    {
        return $this->payroll_status === PayrollStatus::POSTED;
    }

    public function isDraft(): bool
    {
        return $this->payroll_status === PayrollStatus::DRAFT;
    }
}
