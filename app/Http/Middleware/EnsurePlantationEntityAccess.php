<?php

namespace App\Http\Middleware;

use App\Models\PlantationEntity;
use App\Support\PlantationEntityAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlantationEntityAccess
{
    public function __construct(private readonly PlantationEntityAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $entity = $request->route('plantationEntity');

        if (! $entity instanceof PlantationEntity || ! $this->access->isAuthorized($entity)) {
            return response()
                ->view('access.invalid', [], 404)
                ->header('Referrer-Policy', 'no-referrer');
        }

        return $next($request);
    }
}
