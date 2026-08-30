@extends('layouts.plantation')

@section('title', $item->name)

@section('content')
    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-slate-500">Kategori</dt>
                <dd class="font-medium">{{ $item->category->label() }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Satuan</dt>
                <dd>{{ $item->unit }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Stok saat ini</dt>
                <dd class="font-semibold">
                    {{ \App\Support\Quantity::format($currentStock) }}
                    @if ($isLowStock)
                        <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">STOK RENDAH</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Stok minimum</dt>
                <dd>{{ $item->minimum_stock !== null ? \App\Support\Quantity::format($item->minimum_stock) : '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Pembelian terakhir</dt>
                <dd>
                    @if ($lastPurchase)
                        <a class="text-emerald-700 hover:underline" href="{{ route('plantation.purchases.show', [$entity, $lastPurchase]) }}">{{ $lastPurchase->purchase_date->format('d/m/Y') }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Pemakaian terakhir</dt>
                <dd>{{ $lastUsage ? $lastUsage->movement_date->format('d/m/Y') : '—' }}</dd>
            </div>
        </dl>
        <a href="{{ route('plantation.inventory-items.index', $entity) }}" class="mt-4 inline-block text-sm text-emerald-700 hover:underline">Kembali</a>
    </div>

    <h2 class="mb-3 text-sm font-semibold text-slate-900">Histori pergerakan</h2>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Tanggal</th>
                    <th class="px-4 py-3 font-medium">Tipe</th>
                    <th class="px-4 py-3 font-medium">Qty</th>
                    <th class="px-4 py-3 font-medium">Sumber</th>
                    <th class="px-4 py-3 font-medium">Kebun/blok</th>
                    <th class="px-4 py-3 font-medium">Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($movements as $movement)
                    <tr>
                        <td class="px-4 py-3">{{ $movement->movement_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $movement->movement_type->value }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Quantity::format($movement->quantity) }}</td>
                        <td class="px-4 py-3">{{ $movement->source_type->value }} · {{ $movement->source_public_id }}</td>
                        <td class="px-4 py-3">{{ $movement->plantation?->name ?? '—' }}{{ $movement->block ? ' / '.$movement->block->code : '' }}</td>
                        <td class="px-4 py-3">{{ $movement->notes ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">Belum ada pergerakan stok.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $movements->links() }}</div>
@endsection
