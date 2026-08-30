@extends('layouts.plantation')
@section('title', 'Panen')
@section('content')
    <div class="mb-5 flex items-center justify-between">
        <p class="text-sm text-slate-500">Hasil produksi fisik. Panen yang diposting belum menjadi pendapatan Finance.</p>
        <a href="{{ route('plantation.harvests.create', $entity) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white">Tambah panen</a>
    </div>
    @if ($harvests->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">Belum ada panen</div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Tanggal</th>
                        <th class="px-4 py-3 font-medium">Kebun</th>
                        <th class="px-4 py-3 font-medium">Komoditas</th>
                        <th class="px-4 py-3 font-medium">Qty</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($harvests as $harvest)
                        <tr>
                            <td class="px-4 py-3">{{ $harvest->harvest_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $harvest->plantation->name }}{{ $harvest->block ? ' / '.$harvest->block->code : '' }}</td>
                            <td class="px-4 py-3">{{ $harvest->commodity->label() }}</td>
                            <td class="px-4 py-3">{{ \App\Support\Quantity::format($harvest->quantity) }} {{ $harvest->unit }}</td>
                            <td class="px-4 py-3">{{ $harvest->status->label() }}</td>
                            <td class="px-4 py-3 text-right"><a class="text-emerald-700 hover:underline" href="{{ route('plantation.harvests.show', [$entity, $harvest]) }}">Detail</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $harvests->links() }}</div>
    @endif
@endsection
