@extends('layouts.plantation')
@section('title', 'Blok '.$block->code)
@section('content')
    <div class="mb-6 rounded-xl border bg-white p-5 text-sm">
        <p class="font-semibold">{{ $block->plantation->name }} / {{ $block->code }}</p>
        <p class="text-slate-500">Luas: {{ $block->area !== null ? \App\Support\Quantity::format($block->area) : '—' }} ha · Tanaman: {{ $block->crop_type ?: '—' }}</p>
        <p class="mt-2">Panen terakhir: {{ $metrics['last_harvest']?->harvest_date?->format('d/m/Y') ?? '—' }}</p>
        <a href="{{ route('plantation.blocks.index', $entity) }}" class="mt-3 inline-block text-emerald-700 hover:underline">Kembali</a>
    </div>
    <div class="overflow-hidden rounded-xl border bg-white">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3">Komoditas</th>
                    <th class="px-4 py-3">Bulan ini</th>
                    <th class="px-4 py-3">Tahun ini</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Terjual</th>
                    <th class="px-4 py-3">Yield/ha</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($metrics['groups'] as $group)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $group['commodity_label'] }} ({{ $group['unit'] }})</td>
                        <td class="px-4 py-3">{{ \App\Support\Quantity::format($group['month']) }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Quantity::format($group['year']) }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Quantity::format($group['total']) }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Quantity::format($group['sold']) }}</td>
                        <td class="px-4 py-3">{{ $group['yield_per_hectare'] ? \App\Support\Quantity::format($group['yield_per_hectare']) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Belum ada panen posted.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
