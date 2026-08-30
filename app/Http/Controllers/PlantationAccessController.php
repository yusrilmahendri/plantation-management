<?php

namespace App\Http\Controllers;

use App\Models\PlantationAccessToken;
use App\Models\PlantationEntity;
use App\Support\PlantationEntityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlantationAccessController extends Controller
{
    public function __construct(private readonly PlantationEntityAccess $access) {}

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $hash = PlantationAccessToken::hashToken($token);

        $accessToken = PlantationAccessToken::query()
            ->with('plantationEntity')
            ->where('token_hash', $hash)
            ->first();

        if ($accessToken === null || ! $accessToken->isUsable()) {
            return $this->invalid();
        }

        /** @var PlantationEntity $entity */
        $entity = $accessToken->plantationEntity;

        $accessToken->update([
            'last_used_at' => now(),
        ]);

        $request->session()->regenerate();
        $this->access->grant($entity, $accessToken);

        return redirect()
            ->route('plantation.dashboard', $entity)
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function invalid(): Response
    {
        return response()
            ->view('access.invalid', [], 404)
            ->header('Referrer-Policy', 'no-referrer');
    }
}
