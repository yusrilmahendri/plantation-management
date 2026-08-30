<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Database\Factories\PlantationBlockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantationBlock extends Model
{
    /** @use HasFactory<PlantationBlockFactory> */
    use HasFactory, HasPublicUlid;

    protected $fillable = [
        'plantation_id',
        'code',
        'name',
        'area',
        'crop_type',
        'planting_year',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'planting_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function plantation(): BelongsTo
    {
        return $this->belongsTo(Plantation::class);
    }

    public function harvests(): HasMany
    {
        return $this->hasMany(Harvest::class);
    }

    public function scopeForEntity(Builder $query, PlantationEntity $entity): Builder
    {
        return $query->whereHas(
            'plantation',
            fn (Builder $plantationQuery) => $plantationQuery->where('plantation_entity_id', $entity->id)
        );
    }
}
