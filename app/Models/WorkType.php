<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPlantationEntity;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\WorkTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkType extends Model
{
    /** @use HasFactory<WorkTypeFactory> */
    use BelongsToPlantationEntity, HasFactory, HasPublicUlid;

    protected $fillable = [
        'plantation_entity_id',
        'name',
        'description',
        'default_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
