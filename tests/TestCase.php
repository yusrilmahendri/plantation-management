<?php

namespace Tests;

use App\Models\PlantationAccessToken;
use App\Models\PlantationEntity;
use App\Support\PlantationEntityAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function financeHeaders(string $token = 'testing-finance-service-token'): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    protected function issueToken(PlantationEntity $entity, array $attributes = []): array
    {
        $plain = PlantationAccessToken::generatePlainToken();

        $token = PlantationAccessToken::factory()->create([
            'plantation_entity_id' => $entity->id,
            'token_hash' => PlantationAccessToken::hashToken($plain),
            ...$attributes,
        ]);

        return [$token, $plain];
    }

    protected function grantAccess(PlantationEntity $entity, ?PlantationAccessToken $token = null): PlantationAccessToken
    {
        if ($token === null) {
            [$token] = $this->issueToken($entity);
        }

        $this->withSession([
            PlantationEntityAccess::SESSION_KEY => [
                $entity->public_id => [
                    'plantation_entity_public_id' => $entity->public_id,
                    'token_id' => $token->id,
                    'granted_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        return $token;
    }
}
