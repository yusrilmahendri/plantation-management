<?php

namespace Tests\Feature;

use App\Models\PlantationEntity;
use Tests\TestCase;

class InternalApiTest extends TestCase
{
    public function test_request_without_bearer_token_returns_401(): void
    {
        $this->postJson('/api/internal/plantation-entities', [
            'name' => 'Kebun A',
        ])->assertUnauthorized();
    }

    public function test_request_with_wrong_token_returns_401(): void
    {
        $this->postJson('/api/internal/plantation-entities', [
            'name' => 'Kebun A',
        ], $this->financeHeaders('wrong-token'))->assertUnauthorized();
    }

    public function test_valid_token_can_create_entity(): void
    {
        $response = $this->postJson('/api/internal/plantation-entities', [
            'name' => 'Kebun Sungai Raya',
            'finance_entity_public_id' => 'fin_abc123',
            'description' => 'Unit kebun sawit',
        ], $this->financeHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Kebun Sungai Raya')
            ->assertJsonPath('data.finance_entity_public_id', 'fin_abc123')
            ->assertJsonPath('data.is_active', true);

        $this->assertNotEmpty($response->json('data.public_id'));
        $this->assertNotEmpty($response->json('data.slug'));
        $this->assertDatabaseHas('plantation_entities', [
            'name' => 'Kebun Sungai Raya',
            'finance_entity_public_id' => 'fin_abc123',
        ]);
    }

    public function test_valid_token_can_issue_access_link(): void
    {
        $entity = PlantationEntity::factory()->create();

        $response = $this->postJson(
            '/api/internal/plantation-entities/'.$entity->public_id.'/access-links',
            ['label' => 'Mandor'],
            $this->financeHeaders()
        );

        $response->assertCreated()
            ->assertJsonPath('data.label', 'Mandor')
            ->assertJsonStructure(['data' => ['id', 'token', 'access_url', 'is_active']]);

        $this->assertSame(64, strlen($response->json('data.token')));

        $this->postJson(
            '/api/internal/plantation-entities/'.$entity->public_id.'/access-links',
            ['label' => 'Mandor']
        )->assertUnauthorized();
    }

    public function test_entity_can_be_updated_activated_and_deactivated(): void
    {
        $entity = PlantationEntity::factory()->create();

        $this->putJson(
            '/api/internal/plantation-entities/'.$entity->public_id,
            ['name' => 'Nama Baru', 'description' => 'Diubah'],
            $this->financeHeaders()
        )->assertOk()->assertJsonPath('data.name', 'Nama Baru');

        $this->postJson(
            '/api/internal/plantation-entities/'.$entity->public_id.'/deactivate',
            [],
            $this->financeHeaders()
        )->assertOk()->assertJsonPath('data.is_active', false);

        $this->postJson(
            '/api/internal/plantation-entities/'.$entity->public_id.'/activate',
            [],
            $this->financeHeaders()
        )->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_access_link_can_be_revoked_activated_and_deleted(): void
    {
        $entity = PlantationEntity::factory()->create();
        [$token] = $this->issueToken($entity);

        $this->postJson(
            '/api/internal/plantation-entities/'.$entity->public_id.'/access-links/'.$token->id.'/revoke',
            [],
            $this->financeHeaders()
        )->assertOk()->assertJsonPath('data.is_active', false);

        $this->postJson(
            '/api/internal/plantation-entities/'.$entity->public_id.'/access-links/'.$token->id.'/activate',
            [],
            $this->financeHeaders()
        )->assertOk()->assertJsonPath('data.is_active', true);

        $this->deleteJson(
            '/api/internal/plantation-entities/'.$entity->public_id.'/access-links/'.$token->id,
            [],
            $this->financeHeaders()
        )->assertOk();

        $this->assertDatabaseMissing('plantation_access_tokens', ['id' => $token->id]);
    }

    public function test_public_id_is_immutable_via_update(): void
    {
        $entity = PlantationEntity::factory()->create();
        $original = $entity->public_id;

        $this->putJson(
            '/api/internal/plantation-entities/'.$entity->public_id,
            ['public_id' => '01HACKEDPUBLICIDVALUE00000', 'name' => $entity->name],
            $this->financeHeaders()
        )->assertOk();

        $this->assertSame($original, $entity->fresh()->public_id);
    }

    public function test_access_links_can_be_listed_with_metadata_only(): void
    {
        $entity = PlantationEntity::factory()->create();
        $other = PlantationEntity::factory()->create();
        [$token, $plain] = $this->issueToken($entity, ['label' => 'Mandor']);
        $this->issueToken($other, ['label' => 'Foreign']);

        $this->getJson(
            '/api/internal/plantation-entities/'.$entity->public_id.'/access-links'
        )->assertUnauthorized();

        $this->getJson(
            '/api/internal/plantation-entities/'.$entity->public_id.'/access-links',
            $this->financeHeaders('wrong-token')
        )->assertUnauthorized();

        $response = $this->getJson(
            '/api/internal/plantation-entities/'.$entity->public_id.'/access-links',
            $this->financeHeaders()
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $token->id)
            ->assertJsonPath('data.0.label', 'Mandor')
            ->assertJsonMissingPath('data.0.token')
            ->assertJsonMissingPath('data.0.token_hash')
            ->assertJsonMissingPath('data.0.access_url');

        $this->assertStringNotContainsString($plain, (string) json_encode($response->json()));
        $this->assertArrayNotHasKey('token_hash', $response->json('data.0'));
    }
}
