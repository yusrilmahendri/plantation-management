@extends('layouts.plantation')

@section('title', 'Detail pembelian')

@section('content')
    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-slate-500">Tanggal</dt>
                <dd>{{ $purchase->purchase_date->format('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Status</dt>
                <dd>{{ $purchase->status->label() }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Supplier</dt>
                <dd>{{ $purchase->supplier?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Invoice</dt>
                <dd>{{ $purchase->invoice_number ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Subtotal</dt>
                <dd>{{ \App\Support\Money::format($purchase->subtotal) }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Penyesuaian</dt>
                <dd>{{ \App\Support\Money::format($purchase->adjustment_amount) }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Total</dt>
                <dd class="font-semibold">{{ \App\Support\Money::format($purchase->total_amount) }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Realisasi anggaran</dt>
                <dd>{{ $purchase->budgetRealization?->public_id ?? '—' }}</dd>
            </div>
        </dl>
        <div class="mt-4 flex flex-wrap gap-3">
            @if ($purchase->isDraft())
                <a href="{{ route('plantation.purchases.edit', [$entity, $purchase]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Ubah</a>
                <form action="{{ route('plantation.purchases.post', [$entity, $purchase]) }}" method="POST">
                    @csrf
                    <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white">Posting</button>
                </form>
                <form action="{{ route('plantation.purchases.destroy', [$entity, $purchase]) }}" method="POST" onsubmit="return confirm('Hapus draft?')">
                    @csrf
                    @method('DELETE')
                    <button class="text-sm text-red-700">Hapus draft</button>
                </form>
            @endif
            @if (! $purchase->isCancelled())
                <form action="{{ route('plantation.purchases.cancel', [$entity, $purchase]) }}" method="POST" class="flex gap-2">
                    @csrf
                    <input name="reason" required placeholder="Alasan pembatalan" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-700">Batalkan</button>
                </form>
            @endif
            <a href="{{ route('plantation.purchases.index', $entity) }}" class="text-sm text-emerald-700 hover:underline">Kembali</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Barang</th>
                    <th class="px-4 py-3 font-medium">Qty</th>
                    <th class="px-4 py-3 font-medium">Harga</th>
                    <th class="px-4 py-3 font-medium">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($purchase->items as $line)
                    <tr>
                        <td class="px-4 py-3">{{ $line->inventoryItem->name }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Quantity::format($line->quantity) }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Money::format($line->unit_cost) }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Money::format($line->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
