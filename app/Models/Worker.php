<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPlantationEntity;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\WorkerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worker extends Model
{
    /** @use HasFactory<WorkerFactory> */
    use BelongsToPlantationEntity, HasFactory, HasPublicUlid;

    protected $fillable = [
        'plantation_entity_id',
        'name',
        'phone',
        'address',
        'employment_type',
        'daily_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(WorkAttendance::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(WorkerPayroll::class);
    }
}
