@extends('layouts.plantation')

@section('title', 'Pembelian')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <p class="text-sm text-slate-500">Transaksi pembelian barang. Draft dapat diubah; posted mengunci nilai keuangan.</p>
        <a href="{{ route('plantation.purchases.create', $entity) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Tambah pembelian</a>
    </div>
    @if ($purchases->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="font-medium text-slate-800">Belum ada pembelian</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Tanggal</th>
                        <th class="px-4 py-3 font-medium">Supplier</th>
                        <th class="px-4 py-3 font-medium">Total</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($purchases as $purchase)
                        <tr>
                            <td class="px-4 py-3">{{ $purchase->purchase_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $purchase->supplier?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ \App\Support\Money::format($purchase->total_amount) }}</td>
                            <td class="px-4 py-3">{{ $purchase->status->label() }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('plantation.purchases.show', [$entity, $purchase]) }}" class="text-emerald-700 hover:underline">Detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $purchases->links() }}</div>
    @endif
@endsection
