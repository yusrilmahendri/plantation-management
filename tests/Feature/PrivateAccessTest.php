<?php

namespace Tests\Feature;

use App\Models\PlantationAccessToken;
use App\Models\PlantationEntity;
use App\Support\PlantationEntityAccess;
use Tests\TestCase;

class PrivateAccessTest extends TestCase
{
    public function test_valid_token_grants_access(): void
    {
        $entity = PlantationEntity::factory()->create();
        [, $plain] = $this->issueToken($entity);

        $this->get('/access/'.$plain)
            ->assertRedirect(route('plantation.dashboard', $entity))
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->get(route('plantation.dashboard', $entity))
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->get('/access/'.str_repeat('a', 64))
            ->assertNotFound()
            ->assertSee('Akses tidak valid');
    }

    public function test_revoked_token_is_rejected(): void
    {
        $entity = PlantationEntity::factory()->create();
        [, $plain] = $this->issueToken($entity, ['is_active' => false]);

        $this->get('/access/'.$plain)
            ->assertNotFound()
            ->assertSee('Akses tidak valid');
    }

    public function test_expired_token_is_rejected(): void
    {
        $entity = PlantationEntity::factory()->create();
        [, $plain] = $this->issueToken($entity, ['expires_at' => now()->subMinute()]);

        $this->get('/access/'.$plain)
            ->assertNotFound()
            ->assertSee('Akses tidak valid');
    }

    public function test_inactive_entity_is_rejected(): void
    {
        $entity = PlantationEntity::factory()->inactive()->create();
        [, $plain] = $this->issueToken($entity);

        $this->get('/access/'.$plain)
            ->assertNotFound()
            ->assertSee('Akses tidak valid');
    }

    public function test_token_plaintext_is_not_stored(): void
    {
        $entity = PlantationEntity::factory()->create();

        $response = $this->postJson(
            '/api/internal/plantation-entities/'.$entity->public_id.'/access-links',
            ['label' => 'Tim kebun'],
            $this->financeHeaders()
        );

        $response->assertCreated();
        $plain = $response->json('data.token');

        $this->assertNotEmpty($plain);
        $this->assertDatabaseMissing('plantation_access_tokens', [
            'token_hash' => $plain,
        ]);
        $this->assertDatabaseHas('plantation_access_tokens', [
            'token_hash' => PlantationAccessToken::hashToken($plain),
        ]);
        $this->assertArrayNotHasKey('token_hash', $response->json('data'));
    }

    public function test_regenerate_invalidates_the_old_token(): void
    {
        $entity = PlantationEntity::factory()->create();
        [$oldToken, $oldPlain] = $this->issueToken($entity);

        $response = $this->postJson(
            '/api/internal/plantation-entities/'.$entity->public_id.'/access-links/'.$oldToken->id.'/regenerate',
            [],
            $this->financeHeaders()
        );

        $response->assertOk();
        $newPlain = $response->json('data.token');

        $this->get('/access/'.$oldPlain)->assertNotFound();
        $this->get('/access/'.$newPlain)->assertRedirect(route('plantation.dashboard', $entity));

        $oldToken->refresh();
        $this->assertFalse($oldToken->is_active);
        $this->assertNotSame($oldToken->id, $response->json('data.id'));
    }

    public function test_session_capability_applies_only_to_related_entity(): void
    {
        $entityA = PlantationEntity::factory()->create();
        $entityB = PlantationEntity::factory()->create();
        $this->grantAccess($entityA);

        $this->get(route('plantation.dashboard', $entityA))->assertOk();
        $this->get(route('plantation.dashboard', $entityB))
            ->assertNotFound()
            ->assertSee('Akses tidak valid');
    }

    public function test_revoke_after_grant_blocks_the_next_request(): void
    {
        $entity = PlantationEntity::factory()->create();
        $token = $this->grantAccess($entity);

        $this->get(route('plantation.dashboard', $entity))->assertOk();

        $token->update(['is_active' => false]);

        $this->get(route('plantation.dashboard', $entity))
            ->assertNotFound()
            ->assertSee('Akses tidak valid');
    }

    public function test_access_controller_does_not_put_plain_token_in_session(): void
    {
        $entity = PlantationEntity::factory()->create();
        [, $plain] = $this->issueToken($entity);

        $this->get('/access/'.$plain);

        $session = session(PlantationEntityAccess::SESSION_KEY);
        $encoded = json_encode($session);

        $this->assertStringNotContainsString($plain, $encoded);
    }
}
