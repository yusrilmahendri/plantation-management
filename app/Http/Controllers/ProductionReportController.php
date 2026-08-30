<?php

namespace App\Http\Controllers;

use App\Enums\Commodity;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Services\ProductionReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionReportController extends Controller
{
    public function __construct(private readonly ProductionReportService $reports) {}

    public function show(Request $request, PlantationEntity $plantationEntity): View
    {
        $plantation = null;
        if ($request->filled('plantation_public_id')) {
            $plantation = Plantation::query()
                ->forEntity($plantationEntity)
                ->where('public_id', $request->string('plantation_public_id'))
                ->first();
        }

        $block = null;
        if ($request->filled('plantation_block_public_id')) {
            $block = PlantationBlock::query()
                ->forEntity($plantationEntity)
                ->where('public_id', $request->string('plantation_block_public_id'))
                ->first();
        }

        $filters = [
            'period_start' => $request->input('period_start'),
            'period_end' => $request->input('period_end'),
            'plantation_id' => $plantation?->id,
            'plantation_block_id' => $block?->id,
            'commodity' => $request->input('commodity'),
        ];

        return view('production-reports.show', [
            'entity' => $plantationEntity,
            'filters' => $request->only(['period_start', 'period_end', 'plantation_public_id', 'plantation_block_public_id', 'commodity']),
            'summary' => $this->reports->summary($plantationEntity, $filters),
            'plantations' => Plantation::query()->forEntity($plantationEntity)->orderBy('name')->get(),
            'blocks' => PlantationBlock::query()->forEntity($plantationEntity)->with('plantation')->orderBy('code')->get(),
            'commodities' => Commodity::cases(),
        ]);
    }
}
