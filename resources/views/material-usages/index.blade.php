@extends('layouts.plantation')

@section('title', 'Pemakaian stok')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <p class="text-sm text-slate-500">Pemakaian stok operasional, termasuk herbisida. Tidak menambah realisasi anggaran.</p>
        <a href="{{ route('plantation.material-usages.create', $entity) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Tambah pemakaian</a>
    </div>
    @if ($usages->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="font-medium text-slate-800">Belum ada pemakaian</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Tanggal</th>
                        <th class="px-4 py-3 font-medium">Kebun</th>
                        <th class="px-4 py-3 font-medium">Blok</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($usages as $usage)
                        <tr>
                            <td class="px-4 py-3">{{ $usage->usage_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $usage->plantation->name }}</td>
                            <td class="px-4 py-3">{{ $usage->block?->code ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $usage->status->label() }}</td>
                            <td class="px-4 py-3 text-right"><a class="text-emerald-700 hover:underline" href="{{ route('plantation.material-usages.show', [$entity, $usage]) }}">Detail</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $usages->links() }}</div>
    @endif
@endsection
