@extends('layouts.plantation')

@section('title', 'Penyesuaian stok')

@section('content')
    @php
        $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';
    @endphp
    <form action="{{ route('plantation.stock-adjustments.store', $entity) }}" method="POST" class="max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        <div>
            <label class="text-sm font-medium text-slate-700">Barang</label>
            <select name="inventory_item_public_id" required class="{{ $input }}">
                <option value="">Pilih barang</option>
                @foreach ($items as $item)
                    <option value="{{ $item->public_id }}" @selected(old('inventory_item_public_id') === $item->public_id)>{{ $item->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Jenis</label>
            <select name="movement_type" required class="{{ $input }}">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('movement_type') === $type->value)>{{ $type->value }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Kuantitas</label>
            <input name="quantity" type="number" step="0.001" min="0.001" required value="{{ old('quantity') }}" class="{{ $input }}">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Tanggal</label>
            <input name="movement_date" type="date" required value="{{ old('movement_date', now()->toDateString()) }}" class="{{ $input }}">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Alasan</label>
            <textarea name="reason" required rows="3" class="{{ $input }}">{{ old('reason') }}</textarea>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Simpan</button>
            <a href="{{ route('plantation.inventory-items.index', $entity) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Batal</a>
        </div>
    </form>
@endsection
