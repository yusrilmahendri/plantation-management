<?php

namespace Database\Factories;

use App\Models\PlantationAccessToken;
use App\Models\PlantationEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlantationAccessToken>
 */
class PlantationAccessTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plantation_entity_id' => PlantationEntity::factory(),
            'label' => 'Akses default',
            'token_hash' => PlantationAccessToken::hashToken(PlantationAccessToken::generatePlainToken()),
            'is_active' => true,
            'expires_at' => null,
            'last_used_at' => null,
        ];
    }

    public function forPlainToken(string $plainToken): static
    {
        return $this->state(fn () => [
            'token_hash' => PlantationAccessToken::hashToken($plainToken),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
