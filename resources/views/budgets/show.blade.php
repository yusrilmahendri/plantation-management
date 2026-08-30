@extends('layouts.plantation')

@section('title', $allocation->name)

@section('content')
    @php
        $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';
    @endphp

    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Anggaran milik Finance. Plantation tidak membuat anggaran induk baru.</p>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-slate-500">Ref Finance</dt>
                <dd class="font-mono text-slate-900">{{ $allocation->finance_budget_public_id }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Periode</dt>
                <dd>{{ $allocation->period_start->format('d/m/Y') }} – {{ $allocation->period_end->format('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Alokasi dari Finance</dt>
                <dd class="font-semibold">Rp {{ number_format((float) $allocation->allocated_amount, 0, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Sisa belum dibagi</dt>
                <dd>Rp {{ number_format((float) $allocation->remainingToAllocate(), 0, ',', '.') }}</dd>
            </div>
        </dl>
        <a href="{{ route('plantation.budgets.index', $entity) }}" class="mt-4 inline-block text-sm text-emerald-700 hover:underline">Kembali ke daftar</a>
    </div>

    <div class="mb-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">Tambah pembagian</h2>
        <p class="mt-1 text-sm text-slate-500">Jumlah semua item tidak boleh melebihi alokasi Finance.</p>
        <form action="{{ route('plantation.budgets.items.store', [$entity, $allocation]) }}" method="POST" class="mt-4 grid gap-4 sm:grid-cols-3">
            @csrf
            <div>
                <label for="category" class="text-sm font-medium text-slate-700">Kategori</label>
                <select id="category" name="category" required class="{{ $input }}">
                    @foreach ($categories as $category)
                        <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="name" class="text-sm font-medium text-slate-700">Nama</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="{{ $input }}">
            </div>
            <div>
                <label for="allocated_amount" class="text-sm font-medium text-slate-700">Jumlah</label>
                <input id="allocated_amount" name="allocated_amount" type="number" step="0.01" min="0.01" value="{{ old('allocated_amount') }}" required class="{{ $input }}">
            </div>
            <div class="sm:col-span-3">
                <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Tambah item</button>
            </div>
        </form>
    </div>

    <div class="space-y-6">
        @forelse ($allocation->items as $item)
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $item->name }}</p>
                        <p class="text-sm text-slate-500">{{ $item->category->label() }} · Alokasi Rp {{ number_format((float) $item->allocated_amount, 0, ',', '.') }} · Realisasi Rp {{ number_format((float) $item->realizedTotal(), 0, ',', '.') }} · Sisa Rp {{ number_format((float) $item->remainingToRealize(), 0, ',', '.') }}</p>
                    </div>
                    <form action="{{ route('plantation.budgets.items.destroy', [$entity, $allocation, $item]) }}" method="POST" onsubmit="return confirm('Hapus item ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-700 hover:underline">Hapus item</button>
                    </form>
                </div>

                <form action="{{ route('plantation.budgets.realizations.store', [$entity, $allocation, $item]) }}" method="POST" class="mt-4 grid gap-4 sm:grid-cols-3">
                    @csrf
                    <div>
                        <label class="text-sm font-medium text-slate-700">Jumlah realisasi</label>
                        <input name="amount" type="number" step="0.01" min="0.01" required class="{{ $input }}">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Tanggal</label>
                        <input name="realization_date" type="date" value="{{ now()->toDateString() }}" required class="{{ $input }}">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Keterangan</label>
                        <input name="description" type="text" class="{{ $input }}">
                    </div>
                    <div class="sm:col-span-3">
                        <button type="submit" class="rounded-lg border border-emerald-700 px-4 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-50">Catat realisasi</button>
                    </div>
                </form>

                @if ($item->realizations->isNotEmpty())
                    <table class="mt-4 min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="text-left text-slate-500">
                            <tr>
                                <th class="py-2 font-medium">Tanggal</th>
                                <th class="py-2 font-medium">Jumlah</th>
                                <th class="py-2 font-medium">Sumber</th>
                                <th class="py-2 font-medium">Keterangan</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($item->realizations as $realization)
                                <tr>
                                    <td class="py-2">{{ $realization->realization_date->format('d/m/Y') }}</td>
                                    <td class="py-2">Rp {{ number_format((float) $realization->amount, 0, ',', '.') }}</td>
                                    <td class="py-2">{{ $realization->source_type->value }}</td>
                                    <td class="py-2">{{ $realization->status->value }} · {{ $realization->description ?: '—' }}</td>
                                    <td class="py-2 text-right">
                                        @if ($realization->isActive() && $realization->source_type === \App\Enums\RealizationSourceType::MANUAL)
                                            <form action="{{ route('plantation.budgets.realizations.destroy', [$entity, $allocation, $item, $realization]) }}" method="POST" onsubmit="return confirm('Batalkan realisasi ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-700 hover:underline">Batalkan</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @empty
            <p class="text-sm text-slate-500">Belum ada pembagian. Tambahkan item seperti Upah, Pupuk, atau Cadangan.</p>
        @endforelse
    </div>
@endsection
