<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\StorePlantationEntityRequest;
use App\Http\Requests\Internal\UpdatePlantationEntityRequest;
use App\Http\Resources\Internal\InternalPayload;
use App\Models\PlantationEntity;
use App\Services\PlantationEntityService;
use Illuminate\Http\JsonResponse;

class PlantationEntityController extends Controller
{
    public function __construct(private readonly PlantationEntityService $entities) {}

    public function store(StorePlantationEntityRequest $request): JsonResponse
    {
        $entity = $this->entities->create($request->validated());

        return response()->json([
            'data' => InternalPayload::entity($entity),
        ], 201);
    }

    public function update(UpdatePlantationEntityRequest $request, PlantationEntity $plantationEntity): JsonResponse
    {
        $entity = $this->entities->update($plantationEntity, $request->validated());

        return response()->json([
            'data' => InternalPayload::entity($entity),
        ]);
    }

    public function activate(PlantationEntity $plantationEntity): JsonResponse
    {
        return response()->json([
            'data' => InternalPayload::entity($this->entities->activate($plantationEntity)),
        ]);
    }

    public function deactivate(PlantationEntity $plantationEntity): JsonResponse
    {
        return response()->json([
            'data' => InternalPayload::entity($this->entities->deactivate($plantationEntity)),
        ]);
    }
}
