@extends('layouts.plantation')
@section('title', 'Detail panen')
@section('content')
    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">Kebun</dt><dd>{{ $harvest->plantation->name }}</dd></div>
            <div><dt class="text-slate-500">Blok</dt><dd>{{ $harvest->block?->code ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Tanggal</dt><dd>{{ $harvest->harvest_date->format('d/m/Y') }}</dd></div>
            <div><dt class="text-slate-500">Komoditas</dt><dd>{{ $harvest->commodity->label() }}</dd></div>
            <div><dt class="text-slate-500">Kuantitas</dt><dd>{{ \App\Support\Quantity::format($harvest->quantity) }} {{ $harvest->unit }}</dd></div>
            <div><dt class="text-slate-500">Tandan</dt><dd>{{ $harvest->bunch_count ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Mutu</dt><dd>{{ $harvest->quality_grade ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Status</dt><dd>{{ $harvest->status->label() }}</dd></div>
            <div><dt class="text-slate-500">Terjual</dt><dd>{{ \App\Support\Quantity::format($sold) }} {{ $harvest->unit }}</dd></div>
            <div><dt class="text-slate-500">Tersedia</dt><dd>{{ \App\Support\Quantity::format($available) }} {{ $harvest->unit }}</dd></div>
            <div>
                <dt class="text-slate-500">Aktivitas kerja</dt>
                <dd>
                    @if ($harvest->workActivity)
                        <a class="text-emerald-700 hover:underline" href="{{ route('plantation.work-activities.show', [$entity, $harvest->workActivity]) }}">{{ $harvest->workActivity->title }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            @if ($laborCost !== null)
                <div><dt class="text-slate-500">Biaya upah aktivitas</dt><dd>{{ \App\Support\Money::format($laborCost) }}</dd></div>
                <div><dt class="text-slate-500">Indikasi biaya/unit</dt><dd>{{ $costPerUnit ? \App\Support\Money::format($costPerUnit) : '—' }}</dd></div>
            @endif
        </dl>
        @if ($costNote)<p class="mt-3 text-xs text-slate-500">{{ $costNote }}</p>@endif
        <div class="mt-4 flex flex-wrap gap-3">
            @if ($harvest->isDraft())
                <a href="{{ route('plantation.harvests.edit', [$entity, $harvest]) }}" class="rounded-lg border px-4 py-2 text-sm">Ubah</a>
                <form action="{{ route('plantation.harvests.post', [$entity, $harvest]) }}" method="POST">@csrf<button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">Posting</button></form>
            @endif
            @if (! $harvest->isCancelled())
                <form action="{{ route('plantation.harvests.cancel', [$entity, $harvest]) }}" method="POST" class="flex gap-2">
                    @csrf
                    <input name="reason" required placeholder="Alasan" class="rounded-lg border px-3 py-2 text-sm">
                    <button class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-700">Batalkan</button>
                </form>
            @endif
        </div>
    </div>
    <h2 class="mb-3 text-sm font-semibold">Penjualan terkait</h2>
    <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm">
        @forelse ($harvest->saleItems as $line)
            <p>{{ $line->sale->sale_date->format('d/m/Y') }} · {{ $line->sale->buyer->name }} · {{ \App\Support\Quantity::format($line->quantity) }} {{ $harvest->unit }} · {{ $line->sale->status->label() }}</p>
        @empty
            <p class="text-slate-500">Belum ada penjualan.</p>
        @endforelse
    </div>
@endsection
