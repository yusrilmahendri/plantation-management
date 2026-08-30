@extends('layouts.plantation')

@section('title', 'Inventory')

@section('content')
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            ['label' => 'Item aktif', 'value' => $activeItemCount],
            ['label' => 'Stok rendah', 'value' => $lowStockCount],
            ['label' => 'Pembelian bulan ini', 'value' => \App\Support\Money::format($purchaseValueThisMonth)],
            ['label' => 'Pemakaian bulan ini', 'value' => \App\Support\Quantity::format($usageQtyThisMonth)],
            ['label' => 'Pupuk bulan ini', 'value' => \App\Support\Quantity::format($fertilizerQtyThisMonth)],
        ] as $card)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">{{ $card['label'] }}</p>
                <p class="mt-1 text-xl font-semibold text-slate-900">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Master item inventory pada unit ini. Stok dihitung dari ledger, bukan angka yang disimpan di master.</p>
        <div class="flex gap-2">
            <a href="{{ route('plantation.stock-adjustments.create', $entity) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700">Penyesuaian stok</a>
            <a href="{{ route('plantation.inventory-items.create', $entity) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Tambah item</a>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="font-medium text-slate-800">Belum ada item inventory</p>
            <p class="mt-1 text-sm text-slate-500">Tambahkan item seperti pupuk, herbisida, atau alat.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nama</th>
                        <th class="px-4 py-3 font-medium">Kategori</th>
                        <th class="px-4 py-3 font-medium">Satuan</th>
                        <th class="px-4 py-3 font-medium">Stok</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($items as $item)
                        @php
                            $stock = $stocks[$item->id] ?? '0.000';
                            $low = $stockService->isLowStock($item, $stock);
                        @endphp
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                <a href="{{ route('plantation.inventory-items.show', [$entity, $item]) }}" class="hover:underline">{{ $item->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $item->category->label() }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $item->unit }}</td>
                            <td class="px-4 py-3">
                                {{ \App\Support\Quantity::format($stock) }}
                                @if ($low)
                                    <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">STOK RENDAH</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $item->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('plantation.inventory-items.edit', [$entity, $item]) }}" class="text-emerald-700 hover:underline">Ubah</a>
                                <form action="{{ route('plantation.inventory-items.destroy', [$entity, $item]) }}" method="POST" class="ml-3 inline" onsubmit="return confirm('Nonaktifkan atau hapus item ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-700 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $items->links() }}</div>
    @endif
@endsection
