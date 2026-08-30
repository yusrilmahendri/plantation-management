@extends('layouts.plantation')
@section('title', 'Detail penjualan')
@section('content')
    @php $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm'; @endphp
    <div class="mb-6 rounded-xl border bg-white p-5 shadow-sm">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">Pembeli</dt><dd>{{ $sale->buyer->name }}</dd></div>
            <div><dt class="text-slate-500">Tanggal</dt><dd>{{ $sale->sale_date->format('d/m/Y') }}</dd></div>
            <div><dt class="text-slate-500">Invoice</dt><dd>{{ $sale->invoice_number ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Status</dt><dd>{{ $sale->status->label() }} · {{ $sale->payment_status->label() }}</dd></div>
            <div><dt class="text-slate-500">Subtotal</dt><dd>{{ \App\Support\Money::format($sale->subtotal) }}</dd></div>
            <div><dt class="text-slate-500">Penyesuaian</dt><dd>{{ \App\Support\Money::format($sale->adjustment_amount) }}</dd></div>
            <div><dt class="text-slate-500">Total</dt><dd class="font-semibold">{{ \App\Support\Money::format($sale->total_amount) }}</dd></div>
            <div><dt class="text-slate-500">Dibayar</dt><dd>{{ \App\Support\Money::format($sale->paidAmount()) }}</dd></div>
            <div><dt class="text-slate-500">Outstanding</dt><dd>{{ \App\Support\Money::format($sale->outstandingAmount()) }}</dd></div>
        </dl>
        <div class="mt-4 flex flex-wrap gap-3">
            @if ($sale->isDraft())
                <a href="{{ route('plantation.harvest-sales.edit', [$entity, $sale]) }}" class="rounded-lg border px-4 py-2 text-sm">Ubah</a>
                <form action="{{ route('plantation.harvest-sales.post', [$entity, $sale]) }}" method="POST">@csrf<button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">Posting</button></form>
            @endif
            @if (! $sale->isCancelled())
                <form action="{{ route('plantation.harvest-sales.cancel', [$entity, $sale]) }}" method="POST" class="flex gap-2">
                    @csrf
                    <input name="reason" required placeholder="Alasan" class="rounded-lg border px-3 py-2 text-sm">
                    <button class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-700">Batalkan</button>
                </form>
            @endif
        </div>
    </div>
    <div class="mb-6 overflow-hidden rounded-xl border bg-white">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3">Panen</th>
                    <th class="px-4 py-3">Kebun/blok</th>
                    <th class="px-4 py-3">Komoditas</th>
                    <th class="px-4 py-3">Qty</th>
                    <th class="px-4 py-3">Harga</th>
                    <th class="px-4 py-3">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $line)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $line->harvest->harvest_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $line->harvest->plantation->name }}{{ $line->harvest->block ? ' / '.$line->harvest->block->code : '' }}</td>
                        <td class="px-4 py-3">{{ $line->harvest->commodity->label() }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Quantity::format($line->quantity) }} {{ $line->harvest->unit }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Money::format($line->unit_price) }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Money::format($line->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($sale->isPosted())
        <div class="mb-6 rounded-xl border bg-white p-5">
            <h2 class="font-semibold">Catat pembayaran</h2>
            <form action="{{ route('plantation.harvest-sales.payments.store', [$entity, $sale]) }}" method="POST" class="mt-4 grid gap-3 sm:grid-cols-4">
                @csrf
                <input name="amount" type="number" step="0.01" min="0.01" required placeholder="Jumlah" class="{{ $input }}">
                <input name="payment_date" type="date" required value="{{ now()->toDateString() }}" class="{{ $input }}">
                <select name="payment_method" required class="{{ $input }}">
                    @foreach ($methods as $method)
                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                    @endforeach
                </select>
                <input name="reference_number" placeholder="Referensi" class="{{ $input }}">
                <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">Simpan pembayaran</button>
            </form>
        </div>
    @endif
    <div class="rounded-xl border bg-white p-5">
        <h2 class="font-semibold">Riwayat pembayaran</h2>
        <table class="mt-3 min-w-full text-sm">
            @foreach ($sale->payments as $payment)
                <tr class="border-t">
                    <td class="py-2">{{ $payment->payment_date->format('d/m/Y') }}</td>
                    <td>{{ \App\Support\Money::format($payment->amount) }}</td>
                    <td>{{ $payment->payment_method->label() }}</td>
                    <td>{{ $payment->reference_number ?: '—' }}</td>
                    <td>{{ $payment->status->label() }}</td>
                    <td>
                        @if ($payment->isActive())
                            <form action="{{ route('plantation.harvest-sales.payments.reverse', [$entity, $sale, $payment]) }}" method="POST" class="flex gap-2">
                                @csrf
                                <input name="reason" required placeholder="Alasan" class="rounded border px-2 py-1 text-xs">
                                <button class="text-red-700">Batalkan Pembayaran</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
