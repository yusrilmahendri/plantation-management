<?php

namespace App\Support;

use App\Models\PlantationAccessToken;
use App\Models\PlantationEntity;

class PlantationEntityAccess
{
    public const SESSION_KEY = 'plantation_entity_access';

    /**
     * @return array<string, array{plantation_entity_public_id: string, token_id: int, granted_at: string}>
     */
    public function all(): array
    {
        return session(self::SESSION_KEY, []);
    }

    public function grant(PlantationEntity $entity, PlantationAccessToken $token): void
    {
        $access = $this->all();

        $access[$entity->public_id] = [
            'plantation_entity_public_id' => $entity->public_id,
            'token_id' => $token->id,
            'granted_at' => now()->toIso8601String(),
        ];

        session([self::SESSION_KEY => $access]);
    }

    public function tokenIdFor(string $plantationEntityPublicId): ?int
    {
        $entry = $this->all()[$plantationEntityPublicId] ?? null;

        return isset($entry['token_id']) ? (int) $entry['token_id'] : null;
    }

    public function hasCapability(string $plantationEntityPublicId): bool
    {
        return array_key_exists($plantationEntityPublicId, $this->all());
    }

    public function isAuthorized(PlantationEntity $entity): bool
    {
        $tokenId = $this->tokenIdFor($entity->public_id);

        if ($tokenId === null) {
            return false;
        }

        $token = PlantationAccessToken::query()
            ->with('plantationEntity')
            ->find($tokenId);

        if ($token === null) {
            return false;
        }

        if ((int) $token->plantation_entity_id !== (int) $entity->id) {
            return false;
        }

        if ($token->plantationEntity === null || $token->plantationEntity->public_id !== $entity->public_id) {
            return false;
        }

        return $token->isUsable();
    }
}
