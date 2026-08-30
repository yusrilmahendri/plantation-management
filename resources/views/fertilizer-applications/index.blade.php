@extends('layouts.plantation')

@section('title', 'Pemupukan')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <p class="text-sm text-slate-500">Pemupukan khusus. Mengurangi stok pupuk, tanpa realisasi anggaran kedua.</p>
        <a href="{{ route('plantation.fertilizer-applications.create', $entity) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Tambah pemupukan</a>
    </div>
    @if ($applications->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="font-medium text-slate-800">Belum ada pemupukan</p>
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
                    @foreach ($applications as $application)
                        <tr>
                            <td class="px-4 py-3">{{ $application->application_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $application->plantation->name }}</td>
                            <td class="px-4 py-3">{{ $application->block->code }}</td>
                            <td class="px-4 py-3">{{ $application->status->label() }}</td>
                            <td class="px-4 py-3 text-right"><a class="text-emerald-700 hover:underline" href="{{ route('plantation.fertilizer-applications.show', [$entity, $application]) }}">Detail</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $applications->links() }}</div>
    @endif
@endsection
