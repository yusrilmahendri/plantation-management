@extends('layouts.plantation')

@section('title', $worker->name)

@section('content')
    <p class="mb-4 text-sm text-slate-500">Histori aktivitas dan upah pekerja ini.</p>
    <p class="mb-6"><a href="{{ route('plantation.workers.index', $entity) }}" class="text-emerald-700 hover:underline">Kembali</a></p>

    @if ($worker->attendances->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="text-slate-600">Belum ada aktivitas.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Tanggal</th>
                        <th class="px-4 py-3 font-medium">Aktivitas</th>
                        <th class="px-4 py-3 font-medium">Jenis</th>
                        <th class="px-4 py-3 font-medium">Kehadiran</th>
                        <th class="px-4 py-3 font-medium">Upah</th>
                        <th class="px-4 py-3 font-medium">Bayar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($worker->attendances as $attendance)
                        <tr>
                            <td class="px-4 py-3">{{ $attendance->activity?->activity_date?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">
                                @if ($attendance->activity)
                                    <a href="{{ route('plantation.work-activities.show', [$entity, $attendance->activity]) }}" class="text-emerald-700 hover:underline">{{ $attendance->activity->title }}</a>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $attendance->activity?->workType?->name }}</td>
                            <td class="px-4 py-3">{{ $attendance->attendance_status->label() }}</td>
                            <td class="px-4 py-3">{{ $attendance->payroll ? \App\Support\Money::format($attendance->payroll->final_amount) : '—' }}</td>
                            <td class="px-4 py-3">{{ $attendance->payroll?->payment_status->label() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
