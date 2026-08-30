@extends('layouts.plantation')
@section('title', 'Penjualan hasil kebun')
@section('content')
    <div class="mb-5 flex items-center justify-between">
        <p class="text-sm text-slate-500">Penjualan operasional. Belum tersinkron ke pendapatan Finance.</p>
        <a href="{{ route('plantation.harvest-sales.create', $entity) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">Tambah penjualan</a>
    </div>
    @if ($sales->isEmpty())
        <div class="rounded-xl border border-dashed bg-white px-6 py-12 text-center">Belum ada penjualan</div>
    @else
        <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
            <table class="min-w-full divide-y text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Tanggal</th>
                        <th class="px-4 py-3 font-medium">Pembeli</th>
                        <th class="px-4 py-3 font-medium">Total</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Bayar</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($sales as $sale)
                        <tr>
                            <td class="px-4 py-3">{{ $sale->sale_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $sale->buyer->name }}</td>
                            <td class="px-4 py-3">{{ \App\Support\Money::format($sale->total_amount) }}</td>
                            <td class="px-4 py-3">{{ $sale->status->label() }}</td>
                            <td class="px-4 py-3">{{ $sale->payment_status->label() }}</td>
                            <td class="px-4 py-3 text-right"><a class="text-emerald-700 hover:underline" href="{{ route('plantation.harvest-sales.show', [$entity, $sale]) }}">Detail</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $sales->links() }}</div>
    @endif
@endsection
