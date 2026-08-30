<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\UpsertBudgetAllocationRequest;
use App\Http\Resources\Internal\InternalPayload;
use App\Services\BudgetAllocationService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class BudgetAllocationController extends Controller
{
    public function __construct(private readonly BudgetAllocationService $allocations) {}

    public function upsert(UpsertBudgetAllocationRequest $request, string $budgetPublicId): JsonResponse
    {
        $payload = $request->validated();

        if (isset($payload['budget_public_id']) && $payload['budget_public_id'] !== $budgetPublicId) {
            return response()->json([
                'message' => 'budget_public_id pada body harus sama dengan identitas di URL.',
            ], 422);
        }

        $payload['budget_public_id'] = $budgetPublicId;

        try {
            $allocation = $this->allocations->upsertFromFinance($payload);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => InternalPayload::budgetAllocation($allocation),
        ]);
    }
}
