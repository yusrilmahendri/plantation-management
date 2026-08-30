<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\StoreAccessLinkRequest;
use App\Http\Resources\Internal\InternalPayload;
use App\Models\PlantationAccessToken;
use App\Models\PlantationEntity;
use App\Services\PlantationAccessTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AccessLinkController extends Controller
{
    public function __construct(private readonly PlantationAccessTokenService $tokens) {}

    public function index(PlantationEntity $plantationEntity): JsonResponse
    {
        $links = PlantationAccessToken::query()
            ->where('plantation_entity_id', $plantationEntity->id)
            ->latest()
            ->get()
            ->map(fn (PlantationAccessToken $token) => InternalPayload::accessLinkMetadata($token))
            ->values();

        return response()->json([
            'data' => $links,
        ]);
    }

    public function store(StoreAccessLinkRequest $request, PlantationEntity $plantationEntity): JsonResponse
    {
        $expiresAt = $request->filled('expires_at')
            ? Carbon::parse($request->input('expires_at'), config('app.timezone'))
            : null;

        $issued = $this->tokens->issue(
            $plantationEntity,
            $request->input('label'),
            $expiresAt
        );

        return response()->json([
            'data' => InternalPayload::accessLink($issued['token'], $issued['plain']),
        ], 201);
    }

    public function revoke(PlantationEntity $plantationEntity, int $tokenId): JsonResponse
    {
        $token = $this->tokens->revoke($this->tokenForEntity($plantationEntity, $tokenId));

        return response()->json([
            'data' => InternalPayload::accessLink($token),
        ]);
    }

    public function activate(PlantationEntity $plantationEntity, int $tokenId): JsonResponse
    {
        $token = $this->tokens->activate($this->tokenForEntity($plantationEntity, $tokenId));

        return response()->json([
            'data' => InternalPayload::accessLink($token),
        ]);
    }

    public function regenerate(PlantationEntity $plantationEntity, int $tokenId): JsonResponse
    {
        $issued = $this->tokens->regenerate($this->tokenForEntity($plantationEntity, $tokenId));

        return response()->json([
            'data' => InternalPayload::accessLink($issued['token'], $issued['plain']),
        ]);
    }

    public function destroy(PlantationEntity $plantationEntity, int $tokenId): JsonResponse
    {
        $this->tokens->delete($this->tokenForEntity($plantationEntity, $tokenId));

        return response()->json([
            'data' => null,
        ]);
    }

    private function tokenForEntity(PlantationEntity $entity, int $tokenId): PlantationAccessToken
    {
        return PlantationAccessToken::query()
            ->where('plantation_entity_id', $entity->id)
            ->whereKey($tokenId)
            ->firstOrFail();
    }
}
