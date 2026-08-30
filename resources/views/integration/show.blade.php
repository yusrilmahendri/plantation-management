@extends('layouts.plantation')

@section('title', 'Integrasi Finance')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-slate-900">Integrasi Finance</h1>
        <p class="mt-1 text-sm text-slate-600">
            Event keuangan dikirim lewat outbox. Operasi kebun tetap berhasil meski Finance sedang down.
        </p>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Pending</p>
            <p class="mt-1 text-2xl font-semibold">{{ $pendingCount }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Failed</p>
            <p class="mt-1 text-2xl font-semibold text-rose-700">{{ $failedCount }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Last success</p>
            <p class="mt-1 text-sm font-medium">{{ $lastSuccessfulAt?->format('d/m/Y H:i') ?: '—' }}</p>
            <p class="mt-1 text-xs text-slate-500">Events {{ $eventsEnabled ? 'aktif' : 'nonaktif' }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2">Event</th>
                    <th class="px-4 py-2">Source</th>
                    <th class="px-4 py-2">Attempts</th>
                    <th class="px-4 py-2">Error</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentFailed as $row)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2 font-medium">{{ $row->event_type->value }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $row->source_public_id }}</td>
                        <td class="px-4 py-2">{{ $row->attempts }}</td>
                        <td class="px-4 py-2 text-rose-700">{{ $row->last_error }}</td>
                        <td class="px-4 py-2 text-right">
                            <form method="POST" action="{{ route('plantation.integration.retry', [$entity, $row]) }}">
                                @csrf
                                <button class="text-emerald-700 hover:underline" type="submit">Retry</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-6 text-slate-500" colspan="5">Tidak ada event gagal.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
