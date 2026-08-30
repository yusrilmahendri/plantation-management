<?php

namespace App\Http\Controllers;

use App\Enums\ProductionDocumentStatus;
use App\Http\Requests\StoreBuyerRequest;
use App\Http\Requests\UpdateBuyerRequest;
use App\Models\Buyer;
use App\Models\HarvestSale;
use App\Models\PlantationEntity;
use App\Support\EntityRouteBinder;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BuyerController extends Controller
{
    public function index(PlantationEntity $plantationEntity): View
    {
        $buyers = Buyer::query()->forEntity($plantationEntity)->orderBy('name')->paginate(15);

        return view('buyers.index', [
            'entity' => $plantationEntity,
            'buyers' => $buyers,
        ]);
    }

    public function create(PlantationEntity $plantationEntity): View
    {
        return view('buyers.create', ['entity' => $plantationEntity]);
    }

    public function store(StoreBuyerRequest $request, PlantationEntity $plantationEntity): RedirectResponse
    {
        $plantationEntity->buyers()->create($request->validated());

        return redirect()
            ->route('plantation.buyers.index', $plantationEntity)
            ->with('success', 'Pembeli ditambahkan.');
    }

    public function show(PlantationEntity $plantationEntity, Buyer $buyer): View
    {
        EntityRouteBinder::assertOwnedBy($buyer, $plantationEntity);

        $posted = HarvestSale::query()
            ->where('buyer_id', $buyer->id)
            ->where('status', ProductionDocumentStatus::POSTED)
            ->with('payments')
            ->latest('sale_date')
            ->get();

        $totalSales = $posted->reduce(fn (string $carry, HarvestSale $sale) => Money::add($carry, $sale->total_amount), '0.00');
        $totalPaid = $posted->reduce(fn (string $carry, HarvestSale $sale) => Money::add($carry, $sale->paidAmount()), '0.00');

        return view('buyers.show', [
            'entity' => $plantationEntity,
            'buyer' => $buyer,
            'sales' => $posted->take(20),
            'saleCount' => $posted->count(),
            'totalSales' => $totalSales,
            'totalPaid' => $totalPaid,
            'totalOutstanding' => Money::cmp(Money::sub($totalSales, $totalPaid), '0') === -1
                ? Money::normalize('0')
                : Money::sub($totalSales, $totalPaid),
            'payments' => $posted->flatMap->payments->sortByDesc('payment_date')->take(20),
        ]);
    }

    public function edit(PlantationEntity $plantationEntity, Buyer $buyer): View
    {
        EntityRouteBinder::assertOwnedBy($buyer, $plantationEntity);

        return view('buyers.edit', [
            'entity' => $plantationEntity,
            'buyer' => $buyer,
        ]);
    }

    public function update(UpdateBuyerRequest $request, PlantationEntity $plantationEntity, Buyer $buyer): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($buyer, $plantationEntity);
        $buyer->update($request->validated());

        return redirect()
            ->route('plantation.buyers.index', $plantationEntity)
            ->with('success', 'Pembeli diperbarui.');
    }

    public function destroy(PlantationEntity $plantationEntity, Buyer $buyer): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($buyer, $plantationEntity);

        if ($buyer->hasSaleHistory()) {
            $buyer->update(['is_active' => false]);

            return redirect()
                ->route('plantation.buyers.index', $plantationEntity)
                ->with('success', 'Pembeli dinonaktifkan karena memiliki histori penjualan.');
        }

        $buyer->delete();

        return redirect()
            ->route('plantation.buyers.index', $plantationEntity)
            ->with('success', 'Pembeli dihapus.');
    }
}
