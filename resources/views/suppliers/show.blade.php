@extends('layouts.plantation')

@section('title', $supplier->name)

@section('content')
    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-slate-500">Telepon</dt>
                <dd>{{ $supplier->phone ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Jumlah pembelian posted</dt>
                <dd>{{ $purchaseCount }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Total pembelian posted</dt>
                <dd class="font-semibold">{{ \App\Support\Money::format($purchaseTotal) }}</dd>
            </div>
        </dl>
        <a href="{{ route('plantation.suppliers.index', $entity) }}" class="mt-4 inline-block text-sm text-emerald-700 hover:underline">Kembali</a>
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Tanggal</th>
                    <th class="px-4 py-3 font-medium">Invoice</th>
                    <th class="px-4 py-3 font-medium">Total</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($purchases as $purchase)
                    <tr class="border-t">
                        <td class="px-4 py-3"><a class="text-emerald-700 hover:underline" href="{{ route('plantation.purchases.show', [$entity, $purchase]) }}">{{ $purchase->purchase_date->format('d/m/Y') }}</a></td>
                        <td class="px-4 py-3">{{ $purchase->invoice_number ?: '—' }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Money::format($purchase->total_amount) }}</td>
                        <td class="px-4 py-3">{{ $purchase->status->label() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Belum ada pembelian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
