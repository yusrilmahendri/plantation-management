@extends('layouts.plantation')

@section('title', 'Detail pemakaian')

@section('content')
    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">{{ $usage->plantation->name }}{{ $usage->block ? ' / '.$usage->block->code : '' }} · {{ $usage->usage_date->format('d/m/Y') }} · {{ $usage->status->label() }}</p>
        <div class="mt-4 flex flex-wrap gap-3">
            @if ($usage->isDraft())
                <a href="{{ route('plantation.material-usages.edit', [$entity, $usage]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Ubah</a>
                <form action="{{ route('plantation.material-usages.post', [$entity, $usage]) }}" method="POST">
                    @csrf
                    <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white">Posting</button>
                </form>
            @endif
            @if (! $usage->isCancelled())
                <form action="{{ route('plantation.material-usages.cancel', [$entity, $usage]) }}" method="POST" class="flex gap-2">
                    @csrf
                    <input name="reason" required placeholder="Alasan pembatalan" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-700">Batalkan</button>
                </form>
            @endif
        </div>
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Barang</th>
                    <th class="px-4 py-3 font-medium">Qty</th>
                    <th class="px-4 py-3 font-medium">Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($usage->items as $line)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $line->inventoryItem->name }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Quantity::format($line->quantity) }}</td>
                        <td class="px-4 py-3">{{ $line->notes ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
