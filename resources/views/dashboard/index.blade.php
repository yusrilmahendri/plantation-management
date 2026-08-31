@extends('layouts.plantation')

@section('title', 'Dashboard')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">Dashboard Management Kebun</h2>
            <p class="page-description">Ringkasan operasional, stok, aktivitas, anggaran, dan penjualan untuk unit {{ $entity->name }}.</p>
        </div>
    </div>

    <div class="stat-grid">
        @foreach ([
            ['icon' => 'KB', 'label' => 'Kebun aktif', 'value' => $plantationCount],
            ['icon' => 'BL', 'label' => 'Blok aktif', 'value' => $blockCount],
            ['icon' => 'PK', 'label' => 'Pekerja aktif', 'value' => $workerCount],
            ['icon' => 'SP', 'label' => 'Supplier aktif', 'value' => $supplierCount],
            ['icon' => 'IV', 'label' => 'Inventory aktif', 'value' => $inventoryCount],
            ['icon' => 'SR', 'label' => 'Stok rendah', 'value' => $lowStockCount],
            ['icon' => 'AF', 'label' => 'Anggaran Finance', 'value' => $budgetCount],
            ['icon' => 'AK', 'label' => 'Aktivitas bulan ini', 'value' => $activityCountThisMonth],
            ['icon' => 'UP', 'label' => 'Upah bulan ini (posted)', 'value' => \App\Support\Money::format($postedWagesThisMonth)],
            ['icon' => 'PB', 'label' => 'Pembelian bulan ini', 'value' => \App\Support\Money::format($purchaseValueThisMonth)],
            ['icon' => 'UT', 'label' => 'Upah belum dibayar', 'value' => \App\Support\Money::format($unpaidWages)],
            ['icon' => 'PN', 'label' => 'Panen bulan ini', 'value' => $harvestCountThisMonth],
            ['icon' => 'PJ', 'label' => 'Penjualan bulan ini', 'value' => \App\Support\Money::format($salesThisMonth)],
            ['icon' => 'BY', 'label' => 'Pembayaran diterima bulan ini', 'value' => \App\Support\Money::format($receivedThisMonth)],
            ['icon' => 'PI', 'label' => 'Piutang penjualan', 'value' => \App\Support\Money::format($salesOutstanding)],
        ] as $card)
            <div class="stat-card" data-icon="{{ $card['icon'] }}">
                <p class="stat-label">{{ $card['label'] }}</p>
                <p class="stat-value">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    @if (count($productionGroupsThisMonth) > 0 || count($unsoldGroups) > 0)
        <div class="mt-8 grid gap-4 lg:grid-cols-2">
            <div class="card p-5">
                <h2 class="text-sm font-semibold text-slate-900">Produksi bulan ini</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($productionGroupsThisMonth as $group)
                        <li>{{ $group['commodity_label'] }}: {{ \App\Support\Quantity::format($group['production']) }} {{ $group['unit'] }}</li>
                    @empty
                        <li class="text-slate-500">Belum ada produksi bulan ini.</li>
                    @endforelse
                </ul>
            </div>
            <div class="card p-5">
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
