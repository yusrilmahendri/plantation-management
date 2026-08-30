<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPlantationEntity;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\PlantationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plantation extends Model
{
    /** @use HasFactory<PlantationFactory> */
    use BelongsToPlantationEntity, HasFactory, HasPublicUlid;

    protected $fillable = [
        'plantation_entity_id',
        'name',
        'location',
        'total_area',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'total_area' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(PlantationBlock::class);
    }
}
