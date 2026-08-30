@extends('layouts.plantation')
@section('title', $buyer->name)
@section('content')
    <div class="mb-6 rounded-xl border bg-white p-5 text-sm">
        <p>Total penjualan posted: <strong>{{ \App\Support\Money::format($totalSales) }}</strong></p>
        <p>Total dibayar: <strong>{{ \App\Support\Money::format($totalPaid) }}</strong></p>
        <p>Outstanding: <strong>{{ \App\Support\Money::format($totalOutstanding) }}</strong></p>
        <p>Jumlah penjualan: {{ $saleCount }}</p>
    </div>
    <h2 class="mb-2 font-semibold">Penjualan</h2>
    <div class="mb-6 rounded-xl border bg-white p-5 text-sm">
        @forelse ($sales as $sale)
            <p><a class="text-emerald-700 hover:underline" href="{{ route('plantation.harvest-sales.show', [$entity, $sale]) }}">{{ $sale->sale_date->format('d/m/Y') }}</a> · {{ \App\Support\Money::format($sale->total_amount) }} · {{ $sale->payment_status->label() }}</p>
        @empty
            <p class="text-slate-500">Belum ada penjualan posted.</p>
        @endforelse
    </div>
    <h2 class="mb-2 font-semibold">Pembayaran</h2>
    <div class="rounded-xl border bg-white p-5 text-sm">
        @forelse ($payments as $payment)
            <p>{{ $payment->payment_date->format('d/m/Y') }} · {{ \App\Support\Money::format($payment->amount) }} · {{ $payment->status->label() }}</p>
        @empty
            <p class="text-slate-500">Belum ada pembayaran.</p>
        @endforelse
    </div>
@endsection
