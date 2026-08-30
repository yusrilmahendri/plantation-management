@extends('layouts.plantation')

@section('title', $activity->title)

@section('content')
    @php
        $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';
        $fmt = fn ($amount) => \App\Support\Money::format($amount);
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">{{ $activity->activity_date->format('d/m/Y') }} · {{ $activity->plantation?->name }}{{ $activity->block ? ' · '.$activity->block->code : '' }} · {{ $activity->workType?->name }}</p>
            <p class="mt-1 text-sm">Status: <span class="font-medium">{{ $activity->status->label() }}</span></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('plantation.work-activities.edit', [$entity, $activity]) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">Ubah</a>
            <a href="{{ route('plantation.harvests.create', [$entity, 'work_activity_public_id' => $activity->public_id]) }}" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-sm text-emerald-800">Catat Hasil Panen</a>
            <form action="{{ route('plantation.work-activities.complete', [$entity, $activity]) }}" method="POST">
                @csrf
                <button class="rounded-lg bg-emerald-700 px-3 py-1.5 text-sm text-white">Selesaikan aktivitas</button>
            </form>
            <form action="{{ route('plantation.work-activities.cancel', [$entity, $activity]) }}" method="POST" onsubmit="return confirm('Batalkan aktivitas ini?')">
                @csrf
                <button class="rounded-lg border border-red-300 px-3 py-1.5 text-sm text-red-700">Batalkan</button>
            </form>
        </div>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Pekerja', $summary['workers']],
            ['Hadir', $summary['present']],
            ['Tidak hadir', $summary['absent']],
            ['Payroll draf', $summary['draft']],
            ['Payroll posted', $summary['posted']],
            ['Total upah posted', $fmt($summary['total_wages'])],
            ['Sudah dibayar', $fmt($summary['paid'])],
            ['Belum dibayar', $fmt($summary['unpaid'])],
        ] as $card)
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs text-slate-500">{{ $card[0] }}</p>
                <p class="mt-1 text-lg font-semibold">{{ $card[1] }}</p>
            </div>
        @endforeach
    </div>

    @if ($presentWithoutPayroll->isNotEmpty() && $activity->status->value === 'COMPLETED')
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Ada {{ $presentWithoutPayroll->count() }} kehadiran PRESENT tanpa payroll terposting. Payroll tidak dibuat otomatis.
        </div>
    @endif

    <div class="mb-8 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold text-slate-900">Absensi</h2>
        <form action="{{ route('plantation.work-activities.attendances.store', [$entity, $activity]) }}" method="POST" class="mt-4 grid gap-4 sm:grid-cols-3">
            @csrf
            <div class="sm:col-span-2">
                <label class="text-sm font-medium">Tambah pekerja</label>
                <select name="worker_public_ids[]" multiple size="6" class="{{ $input }}">
                    @foreach ($workers as $worker)
                        <option value="{{ $worker->public_id }}">{{ $worker->name }}{{ $worker->daily_rate ? ' · '.$fmt($worker->daily_rate) : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Status</label>
                <select name="attendance_status" class="{{ $input }}">
                    @foreach ($attendanceStatuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
                <button class="mt-4 rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">Tambah pekerja</button>
            </div>
        </form>

        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="text-left text-slate-500">
                    <tr>
                        <th class="py-2">Pekerja</th>
                        <th class="py-2">Status</th>
                        <th class="py-2">Masuk/Pulang</th>
                        <th class="py-2">Unit</th>
                        <th class="py-2">Estimasi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($activity->attendances as $attendance)
                        <tr>
                            <td class="py-3 font-medium">{{ $attendance->worker?->name }}</td>
                            <td class="py-3">
                                <form action="{{ route('plantation.work-activities.attendances.update', [$entity, $activity, $attendance]) }}" method="POST" class="flex flex-wrap gap-2">
                                    @csrf
                                    @method('PUT')
                                    <select name="attendance_status" class="rounded border-slate-300 text-sm">
                                        @foreach ($attendanceStatuses as $status)
                                            <option value="{{ $status->value }}" @selected($attendance->attendance_status === $status)>{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                    <input type="datetime-local" name="check_in" value="{{ $attendance->check_in?->format('Y-m-d\\TH:i') }}" class="rounded border-slate-300 text-sm">
                                    <input type="datetime-local" name="check_out" value="{{ $attendance->check_out?->format('Y-m-d\\TH:i') }}" class="rounded border-slate-300 text-sm">
                                    <input type="number" step="0.01" min="0" name="work_units" value="{{ $attendance->work_units }}" class="w-24 rounded border-slate-300 text-sm" placeholder="Unit">
                                    <button class="text-emerald-700">Simpan</button>
                                </form>
                            </td>
                            <td class="py-3 text-slate-500">{{ $attendance->check_in?->format('H:i') ?: '—' }} / {{ $attendance->check_out?->format('H:i') ?: '—' }}</td>
                            <td class="py-3">{{ $attendance->work_units ?? '—' }}</td>
                            <td class="py-3">{{ $attendance->worker?->daily_rate ? $fmt($attendance->worker->daily_rate) : ($activity->workType?->default_rate ? $fmt($activity->workType->default_rate) : 'Perlu input') }}</td>
                            <td class="py-3 text-right">
                                <form action="{{ route('plantation.work-activities.attendances.destroy', [$entity, $activity, $attendance]) }}" method="POST" onsubmit="return confirm('Hapus dari aktivitas?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-700">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold text-slate-900">Payroll</h2>
        <p class="mt-1 text-sm text-slate-500">Generate draf dulu, lalu post ke item anggaran Upah. Posting mengunci nominal dan membuat realisasi. Pembayaran tunai tidak menambah realisasi.</p>

        <form action="{{ route('plantation.work-activities.payrolls.generate', [$entity, $activity]) }}" method="POST" class="mt-4 grid gap-4 sm:grid-cols-4">
            @csrf
            <div class="sm:col-span-2">
                <label class="text-sm font-medium">Kehadiran PRESENT</label>
                <select name="attendance_public_ids[]" multiple size="5" class="{{ $input }}">
                    @foreach ($activity->attendances->where('attendance_status', \App\Enums\AttendanceStatus::PRESENT) as $attendance)
                        <option value="{{ $attendance->public_id }}">{{ $attendance->worker?->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Jenis tarif</label>
                <select name="rate_type" class="{{ $input }}">
                    @foreach ($rateTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
                <label class="mt-3 block text-sm font-medium">Override tarif (opsional)</label>
                <input name="rate_amount" type="number" step="0.01" min="0" class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm font-medium">Kuantitas override</label>
                <input name="work_quantity" type="number" step="0.01" min="0" class="{{ $input }}">
                <button class="mt-8 rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">Generate draf</button>
            </div>
        </form>

        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="text-left text-slate-500">
                    <tr>
                        <th class="py-2">Pekerja</th>
                        <th class="py-2">Tarif</th>
                        <th class="py-2">Qty</th>
                        <th class="py-2">Gross</th>
                        <th class="py-2">Adj</th>
                        <th class="py-2">Total</th>
                        <th class="py-2">Payroll</th>
                        <th class="py-2">Bayar</th>
                        <th class="py-2">Anggaran</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($activity->payrolls as $payroll)
                        <tr>
                            <td class="py-3 font-medium">{{ $payroll->worker?->name }}</td>
                            <td class="py-3">{{ $payroll->rate_type->label() }} {{ $fmt($payroll->rate_amount) }}</td>
                            <td class="py-3">{{ $payroll->work_quantity ?? '—' }}</td>
                            <td class="py-3">{{ $fmt($payroll->gross_amount) }}</td>
                            <td class="py-3">{{ $fmt($payroll->adjustment_amount) }}</td>
                            <td class="py-3 font-semibold">{{ $fmt($payroll->final_amount) }}</td>
                            <td class="py-3">{{ $payroll->payroll_status->label() }}</td>
                            <td class="py-3">{{ $payroll->payment_status->label() }}</td>
                            <td class="py-3">{{ $payroll->budgetItem?->name ?? '—' }}</td>
                            <td class="py-3">
                                @if ($payroll->isDraft())
                                    <form action="{{ route('plantation.work-activities.payrolls.update', [$entity, $activity, $payroll]) }}" method="POST" class="mb-2 flex flex-wrap gap-2">
                                        @csrf
                                        @method('PUT')
                                        <select name="rate_type" class="rounded border-slate-300 text-sm">
                                            @foreach ($rateTypes as $type)
                                                <option value="{{ $type->value }}" @selected($payroll->rate_type === $type)>{{ $type->label() }}</option>
                                            @endforeach
                                        </select>
                                        <input name="rate_amount" type="number" step="0.01" value="{{ $payroll->rate_amount }}" class="w-28 rounded border-slate-300 text-sm">
                                        <input name="work_quantity" type="number" step="0.01" value="{{ $payroll->work_quantity }}" class="w-24 rounded border-slate-300 text-sm">
                                        <input name="adjustment_amount" type="number" step="0.01" value="{{ $payroll->adjustment_amount }}" class="w-24 rounded border-slate-300 text-sm">
                                        <button class="text-emerald-700">Ubah draf</button>
                                    </form>
                                    <form action="{{ route('plantation.work-activities.payrolls.post', [$entity, $activity, $payroll]) }}" method="POST" class="mb-2 flex gap-2">
                                        @csrf
                                        <select name="budget_allocation_item_public_id" required class="rounded border-slate-300 text-sm">
                                            <option value="">Item upah</option>
                                            @foreach ($wageItems as $item)
                                                <option value="{{ $item->public_id }}">{{ $item->name }} · sisa {{ $fmt($item->remainingToRealize()) }}</option>
                                            @endforeach
                                        </select>
                                        <button class="text-emerald-700">Posting</button>
                                    </form>
                                    <form action="{{ route('plantation.work-activities.payrolls.cancel', [$entity, $activity, $payroll]) }}" method="POST">
                                        @csrf
                                        <button class="text-red-700">Batalkan draf</button>
                                    </form>
                                @elseif ($payroll->isPosted())
                                    @if ($payroll->payment_status->value === 'UNPAID')
                                        <form action="{{ route('plantation.work-activities.payrolls.pay', [$entity, $activity, $payroll]) }}" method="POST" class="mb-2 flex gap-2">
                                            @csrf
                                            <input type="datetime-local" name="paid_at" required value="{{ now()->format('Y-m-d\\TH:i') }}" class="rounded border-slate-300 text-sm">
                                            <button class="text-emerald-700">Tandai sudah dibayar (CASH)</button>
                                        </form>
                                    @endif
                                    <form action="{{ route('plantation.work-activities.payrolls.cancel', [$entity, $activity, $payroll]) }}" method="POST" onsubmit="return confirm('Batalkan payroll terposting? Realisasi akan di-reverse.')">
                                        @csrf
                                        <button class="text-red-700">Batalkan posting</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
