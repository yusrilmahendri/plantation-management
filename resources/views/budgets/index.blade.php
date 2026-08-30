@extends('layouts.plantation')

@section('title', 'Anggaran')

@section('content')
    <p class="mb-5 text-sm text-slate-500">
        Anggaran induk berasal dari Finance. Unit kebun hanya membagi dan merealisasikan alokasi yang sudah dikirim.
    </p>

    @if ($budgets->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="font-medium text-slate-800">Belum ada anggaran dari Finance</p>
            <p class="mt-1 text-sm text-slate-500">Anggaran akan muncul setelah admin Finance membuat dan mengaktifkan anggaran kebun.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nama</th>
                        <th class="px-4 py-3 font-medium">Periode</th>
                        <th class="px-4 py-3 font-medium">Alokasi</th>
                        <th class="px-4 py-3 font-medium">Terbagi</th>
                        <th class="px-4 py-3 font-medium">Ref Finance</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($budgets as $budget)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $budget->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $budget->period_start->format('d/m/Y') }} – {{ $budget->period_end->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-slate-600">Rp {{ number_format((float) $budget->allocated_amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-slate-600">Rp {{ number_format((float) $budget->itemsAllocatedTotal(), 0, ',', '.') }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $budget->finance_budget_public_id }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('plantation.budgets.show', [$entity, $budget]) }}" class="text-emerald-700 hover:underline">Kelola</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $budgets->links() }}</div>
    @endif
@endsection
