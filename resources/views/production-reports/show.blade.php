@extends('layouts.plantation')
@section('title', 'Laporan produksi')
@section('content')
    @php $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm'; @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">Laporan Produksi</h2>
            <p class="page-description">Ringkasan produksi, penjualan, penerimaan, dan sisa hasil panen berdasarkan filter yang tersedia.</p>
        </div>
    </div>

    <form method="GET" class="filter-card mb-6 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
        <div>
            <label>Tanggal awal</label>
            <input type="date" name="period_start" value="{{ $filters['period_start'] ?? '' }}" class="{{ $input }}">
        </div>
        <div>
            <label>Tanggal akhir</label>
            <input type="date" name="period_end" value="{{ $filters['period_end'] ?? '' }}" class="{{ $input }}">
        </div>
        <div>
            <label>Kebun</label>
            <select name="plantation_public_id" class="{{ $input }}">
                <option value="">Semua kebun</option>
                @foreach ($plantations as $plantation)
                    <option value="{{ $plantation->public_id }}" @selected(($filters['plantation_public_id'] ?? '') === $plantation->public_id)>{{ $plantation->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Blok</label>
            <select name="plantation_block_public_id" class="{{ $input }}">
                <option value="">Semua blok</option>
                @foreach ($blocks as $block)
                    <option value="{{ $block->public_id }}" @selected(($filters['plantation_block_public_id'] ?? '') === $block->public_id)>{{ $block->plantation->name }} / {{ $block->code }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Komoditas</label>
            <select name="commodity" class="{{ $input }}">
                <option value="">Semua komoditas</option>
                @foreach ($commodities as $commodity)
                    <option value="{{ $commodity->value }}" @selected(($filters['commodity'] ?? '') === $commodity->value)>{{ $commodity->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <button class="w-full rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">Filter</button>
        </div>
    </form>

    <div class="mb-6 stat-grid">
        @foreach ([
            ['JP', 'Jumlah panen', $summary['harvest_count']],
            ['NP', 'Nilai penjualan', \App\Support\Money::format($summary['sales_amount'])],
            ['DT', 'Diterima', \App\Support\Money::format($summary['received'])],
            ['OS', 'Outstanding', \App\Support\Money::format($summary['outstanding'])],
        ] as $card)
            <div class="stat-card" data-icon="{{ $card[0] }}">
                <p class="stat-label">{{ $card[1] }}</p>
                <p class="stat-value">{{ $card[2] }}</p>
            </div>
        @endforeach
    </div>

    <div class="table-card">
        <div class="table-responsive">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3">Komoditas</th>
                    <th class="px-4 py-3">Satuan</th>
                    <th class="px-4 py-3">Produksi</th>
                    <th class="px-4 py-3">Panen</th>
                    <th class="px-4 py-3">Terjual</th>
                    <th class="px-4 py-3">Sisa</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summary['groups'] as $group)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $group['commodity_label'] }}</td>
                        <td class="px-4 py-3">{{ $group['unit'] }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Quantity::format($group['production']) }}</td>
                        <td class="px-4 py-3">{{ $group['harvest_count'] }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Quantity::format($group['sold']) }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Quantity::format($group['remaining']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Tidak ada data produksi.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
@endsection
