<?php

namespace App\Models;

use App\Enums\WorkActivityStatus;
use App\Models\Concerns\BelongsToPlantationEntity;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkActivity extends Model
{
    use BelongsToPlantationEntity;
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'plantation_entity_id',
        'plantation_id',
        'plantation_block_id',
        'work_type_id',
        'activity_date',
        'title',
        'description',
        'status',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'status' => WorkActivityStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function plantation(): BelongsTo
    {
        return $this->belongsTo(Plantation::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(PlantationBlock::class, 'plantation_block_id');
    }

    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(WorkAttendance::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(WorkerPayroll::class);
    }

    public function fertilizerApplications(): HasMany
    {
        return $this->hasMany(FertilizerApplication::class);
    }

    public function harvests(): HasMany
    {
        return $this->hasMany(Harvest::class);
    }

    public function allowsPayrollGeneration(): bool
    {
        return $this->status !== WorkActivityStatus::CANCELLED;
    }
}
