@extends('layouts.plantation')
@section('title', 'Pembeli')
@section('content')
    <div class="mb-5 flex items-center justify-between">
        <p class="text-sm text-slate-500">Pembeli hasil kebun pada unit ini.</p>
        <a href="{{ route('plantation.buyers.create', $entity) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">Tambah pembeli</a>
    </div>
    @if ($buyers->isEmpty())
        <div class="rounded-xl border border-dashed bg-white px-6 py-12 text-center">Belum ada pembeli</div>
    @else
        <div class="overflow-hidden rounded-xl border bg-white">
            <table class="min-w-full divide-y text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Telepon</th>
                        <th class="px-4 py-3">Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($buyers as $buyer)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $buyer->name }}</td>
                            <td class="px-4 py-3">{{ $buyer->phone ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $buyer->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a class="text-emerald-700 hover:underline" href="{{ route('plantation.buyers.show', [$entity, $buyer]) }}">Detail</a>
                                <a class="ml-3 text-emerald-700 hover:underline" href="{{ route('plantation.buyers.edit', [$entity, $buyer]) }}">Ubah</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $buyers->links() }}</div>
    @endif
@endsection
