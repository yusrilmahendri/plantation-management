@extends('layouts.plantation')

@section('title', 'Detail pemupukan')

@section('content')
    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">{{ $application->plantation->name }} / {{ $application->block->code }} · {{ $application->application_date->format('d/m/Y') }} · {{ $application->status->label() }}</p>
        <div class="mt-4 flex flex-wrap gap-3">
            @if ($application->isDraft())
                <a href="{{ route('plantation.fertilizer-applications.edit', [$entity, $application]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Ubah</a>
                <form action="{{ route('plantation.fertilizer-applications.post', [$entity, $application]) }}" method="POST">
                    @csrf
                    <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white">Posting</button>
                </form>
            @endif
            @if (! $application->isCancelled())
                <form action="{{ route('plantation.fertilizer-applications.cancel', [$entity, $application]) }}" method="POST" class="flex gap-2">
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
                    <th class="px-4 py-3 font-medium">Pupuk</th>
                    <th class="px-4 py-3 font-medium">Qty</th>
                    <th class="px-4 py-3 font-medium">Dosis/tanaman</th>
                    <th class="px-4 py-3 font-medium">Tanaman</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($application->items as $line)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $line->inventoryItem->name }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Quantity::format($line->quantity) }}</td>
                        <td class="px-4 py-3">{{ $line->dosage_per_plant !== null ? \App\Support\Quantity::format($line->dosage_per_plant) : '—' }}</td>
                        <td class="px-4 py-3">{{ $line->plant_count ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
