@extends('layouts.plantation')
@section('title', 'Laporan produksi')
@section('content')
    @php $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm'; @endphp
    <form method="GET" class="mb-6 grid gap-3 rounded-xl border bg-white p-5 sm:grid-cols-5">
        <input type="date" name="period_start" value="{{ $filters['period_start'] ?? '' }}" class="{{ $input }}">
        <input type="date" name="period_end" value="{{ $filters['period_end'] ?? '' }}" class="{{ $input }}">
        <select name="plantation_public_id" class="{{ $input }}">
            <option value="">Semua kebun</option>
            @foreach ($plantations as $plantation)
                <option value="{{ $plantation->public_id }}" @selected(($filters['plantation_public_id'] ?? '') === $plantation->public_id)>{{ $plantation->name }}</option>
            @endforeach
        </select>
        <select name="plantation_block_public_id" class="{{ $input }}">
            <option value="">Semua blok</option>
            @foreach ($blocks as $block)
                <option value="{{ $block->public_id }}" @selected(($filters['plantation_block_public_id'] ?? '') === $block->public_id)>{{ $block->plantation->name }} / {{ $block->code }}</option>
            @endforeach
        </select>
        <select name="commodity" class="{{ $input }}">
            <option value="">Semua komoditas</option>
            @foreach ($commodities as $commodity)
                <option value="{{ $commodity->value }}" @selected(($filters['commodity'] ?? '') === $commodity->value)>{{ $commodity->label() }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">Filter</button>
    </form>
    <div class="mb-6 grid gap-3 sm:grid-cols-4">
        @foreach ([
            ['Jumlah panen', $summary['harvest_count']],
            ['Nilai penjualan', \App\Support\Money::format($summary['sales_amount'])],
            ['Diterima', \App\Support\Money::format($summary['received'])],
            ['Outstanding', \App\Support\Money::format($summary['outstanding'])],
        ] as $card)
            <div class="rounded-xl border bg-white p-4">
                <p class="text-sm text-slate-500">{{ $card[0] }}</p>
                <p class="mt-1 text-xl font-semibold">{{ $card[1] }}</p>
            </div>
        @endforeach
    </div>
    <div class="overflow-hidden rounded-xl border bg-white">
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
@endsection
