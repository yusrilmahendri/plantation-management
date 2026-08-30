<?php

namespace App\Services;

use App\Models\PlantationEntity;

class PlantationEntityService
{
    public function create(array $attributes): PlantationEntity
    {
        if (empty($attributes['slug'])) {
            $attributes['slug'] = PlantationEntity::generateUniqueSlug($attributes['name']);
        }

        $attributes['is_active'] = $attributes['is_active'] ?? true;

        return PlantationEntity::query()->create($attributes);
    }

    public function update(PlantationEntity $entity, array $attributes): PlantationEntity
    {
        unset($attributes['public_id']);

        if (array_key_exists('slug', $attributes) && blank($attributes['slug'])) {
            $attributes['slug'] = PlantationEntity::generateUniqueSlug(
                $attributes['name'] ?? $entity->name,
                $entity->id
            );
        }

        $entity->update($attributes);

        return $entity->refresh();
    }

    public function activate(PlantationEntity $entity): PlantationEntity
    {
        $entity->update(['is_active' => true]);

        return $entity->refresh();
    }

    public function deactivate(PlantationEntity $entity): PlantationEntity
    {
        $entity->update(['is_active' => false]);

        return $entity->refresh();
    }
}
