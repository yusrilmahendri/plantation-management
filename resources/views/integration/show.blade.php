@extends('layouts.plantation')

@section('title', 'Integrasi Finance')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">Integrasi Finance</h2>
            <p class="page-description">
            Event keuangan dikirim lewat outbox. Operasi kebun tetap berhasil meski Finance sedang down.
            </p>
        </div>
    </div>

    <div class="mb-6 stat-grid">
        <div class="stat-card" data-icon="PN">
            <p class="stat-label">Pending</p>
            <p class="stat-value">{{ $pendingCount }}</p>
        </div>
        <div class="stat-card" data-icon="FL">
            <p class="stat-label">Failed</p>
            <p class="stat-value text-rose-700">{{ $failedCount }}</p>
        </div>
        <div class="stat-card" data-icon="OK">
            <p class="stat-label">Last success</p>
            <p class="stat-value text-base">{{ $lastSuccessfulAt?->format('d/m/Y H:i') ?: '—' }}</p>
            <p class="mt-2 text-xs text-slate-500">Events {{ $eventsEnabled ? 'aktif' : 'nonaktif' }}</p>
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
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
    </div>
@endsection
