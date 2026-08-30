<?php

namespace App\Http\Controllers\Internal;

use App\Enums\ProductionDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Internal\InternalPayload;
use App\Models\HarvestSale;
use App\Models\PlantationEntity;
use Illuminate\Http\JsonResponse;

class HarvestSaleController extends Controller
{
    public function index(PlantationEntity $plantationEntity): JsonResponse
    {
        $sales = HarvestSale::query()
            ->forEntity($plantationEntity)
            ->whereIn('status', [
                ProductionDocumentStatus::POSTED,
                ProductionDocumentStatus::CANCELLED,
            ])
            ->with(['buyer', 'payments'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $sales->map(fn (HarvestSale $sale) => InternalPayload::harvestSale($sale))->values()->all(),
        ]);
    }
}
