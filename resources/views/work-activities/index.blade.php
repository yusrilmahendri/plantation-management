@extends('layouts.plantation')

@section('title', 'Aktivitas Kerja')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <p class="text-sm text-slate-500">Kegiatan kebun, absensi, dan upah pekerja. Pembayaran tunai tidak membuat transaksi Finance.</p>
        <a href="{{ route('plantation.work-activities.create', $entity) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Tambah aktivitas</a>
    </div>

    @if ($activities->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="font-medium text-slate-800">Belum ada aktivitas kerja</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Tanggal</th>
                        <th class="px-4 py-3 font-medium">Judul</th>
                        <th class="px-4 py-3 font-medium">Kebun</th>
                        <th class="px-4 py-3 font-medium">Jenis</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($activities as $activity)
                        <tr>
                            <td class="px-4 py-3">{{ $activity->activity_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $activity->title }}</td>
                            <td class="px-4 py-3">{{ $activity->plantation?->name }}</td>
                            <td class="px-4 py-3">{{ $activity->workType?->name }}</td>
                            <td class="px-4 py-3">{{ $activity->status->label() }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('plantation.work-activities.show', [$entity, $activity]) }}" class="text-emerald-700 hover:underline">Kelola</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $activities->links() }}</div>
    @endif
@endsection
