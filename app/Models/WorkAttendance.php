<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkAttendance extends Model
{
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'work_activity_id',
        'worker_id',
        'attendance_status',
        'check_in',
        'check_out',
        'work_units',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_status' => AttendanceStatus::class,
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'work_units' => 'decimal:2',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(WorkActivity::class, 'work_activity_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function payroll(): HasOne
    {
        return $this->hasOne(WorkerPayroll::class);
    }
}
