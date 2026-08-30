<?php

namespace App\Services;

use App\Models\PlantationAccessToken;
use App\Models\PlantationEntity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PlantationAccessTokenService
{
    /**
     * @return array{token: PlantationAccessToken, plain: string}
     */
    public function issue(PlantationEntity $entity, ?string $label = null, ?Carbon $expiresAt = null): array
    {
        $plain = PlantationAccessToken::generatePlainToken();

        $token = PlantationAccessToken::query()->create([
            'plantation_entity_id' => $entity->id,
            'label' => $label,
            'token_hash' => PlantationAccessToken::hashToken($plain),
            'is_active' => true,
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'plain' => $plain,
        ];
    }

    public function revoke(PlantationAccessToken $token): PlantationAccessToken
    {
        $token->update(['is_active' => false]);

        return $token->refresh();
    }

    public function activate(PlantationAccessToken $token): PlantationAccessToken
    {
        $token->update(['is_active' => true]);

        return $token->refresh();
    }

    /**
     * @return array{token: PlantationAccessToken, plain: string}
     */
    public function regenerate(PlantationAccessToken $token): array
    {
        return DB::transaction(function () use ($token): array {
            $entity = $token->plantationEntity;
            $label = $token->label;
            $expiresAt = $token->expires_at;

            $token->update(['is_active' => false]);

            return $this->issue($entity, $label, $expiresAt);
        });
    }

    public function delete(PlantationAccessToken $token): void
    {
        $token->delete();
    }
}
