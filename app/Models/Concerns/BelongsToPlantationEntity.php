<?php

namespace App\Models\Concerns;

use App\Models\PlantationEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToPlantationEntity
{
    public function plantationEntity(): BelongsTo
    {
        return $this->belongsTo(PlantationEntity::class);
    }

    public function scopeForEntity(Builder $query, PlantationEntity $entity): Builder
    {
        return $query->where($this->qualifyColumn('plantation_entity_id'), $entity->id);
    }
}
