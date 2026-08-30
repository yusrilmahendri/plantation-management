@extends('layouts.plantation')

@section('title', 'Kebun')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <p class="text-sm text-slate-500">Daftar kebun pada unit ini.</p>
        <a href="{{ route('plantation.plantations.create', $entity) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Tambah kebun</a>
    </div>

    @if ($plantations->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="font-medium text-slate-800">Belum ada kebun</p>
            <p class="mt-1 text-sm text-slate-500">Tambahkan kebun pertama untuk unit ini.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nama</th>
                        <th class="px-4 py-3 font-medium">Lokasi</th>
                        <th class="px-4 py-3 font-medium">Luas</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($plantations as $plantation)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $plantation->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $plantation->location ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $plantation->total_area !== null ? $plantation->total_area : '—' }}</td>
                            <td class="px-4 py-3">{{ $plantation->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('plantation.plantations.edit', [$entity, $plantation]) }}" class="text-emerald-700 hover:underline">Ubah</a>
                                <form action="{{ route('plantation.plantations.destroy', [$entity, $plantation]) }}" method="POST" class="ml-3 inline" onsubmit="return confirm('Hapus kebun ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-700 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $plantations->links() }}</div>
    @endif
@endsection
