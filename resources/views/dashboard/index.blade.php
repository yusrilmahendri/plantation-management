@extends('layouts.plantation')

@section('title', 'Dashboard')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Kebun aktif', 'value' => $plantationCount],
            ['label' => 'Blok aktif', 'value' => $blockCount],
            ['label' => 'Pekerja aktif', 'value' => $workerCount],
            ['label' => 'Supplier aktif', 'value' => $supplierCount],
            ['label' => 'Inventory aktif', 'value' => $inventoryCount],
            ['label' => 'Stok rendah', 'value' => $lowStockCount],
            ['label' => 'Anggaran Finance', 'value' => $budgetCount],
            ['label' => 'Aktivitas bulan ini', 'value' => $activityCountThisMonth],
            ['label' => 'Upah bulan ini (posted)', 'value' => \App\Support\Money::format($postedWagesThisMonth)],
            ['label' => 'Pembelian bulan ini', 'value' => \App\Support\Money::format($purchaseValueThisMonth)],
            ['label' => 'Upah belum dibayar', 'value' => \App\Support\Money::format($unpaidWages)],
            ['label' => 'Panen bulan ini', 'value' => $harvestCountThisMonth],
            ['label' => 'Penjualan bulan ini', 'value' => \App\Support\Money::format($salesThisMonth)],
            ['label' => 'Pembayaran diterima bulan ini', 'value' => \App\Support\Money::format($receivedThisMonth)],
            ['label' => 'Piutang penjualan', 'value' => \App\Support\Money::format($salesOutstanding)],
        ] as $card)
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">{{ $card['label'] }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    @if (count($productionGroupsThisMonth) > 0 || count($unsoldGroups) > 0)
        <div class="mt-8 grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">Produksi bulan ini</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($productionGroupsThisMonth as $group)
                        <li>{{ $group['commodity_label'] }}: {{ \App\Support\Quantity::format($group['production']) }} {{ $group['unit'] }}</li>
                    @empty
                        <li class="text-slate-500">Belum ada produksi bulan ini.</li>
                    @endforelse
                </ul>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">Hasil panen belum terjual</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($unsoldGroups as $group)
                        <li>{{ $group['label'] }}: {{ \App\Support\Quantity::format($group['quantity']) }}</li>
                    @empty
                        <li class="text-slate-500">Tidak ada sisa hasil panen.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif
@endsection
