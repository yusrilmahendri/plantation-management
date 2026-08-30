@php
    $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';
@endphp

<div>
    <label for="name" class="text-sm font-medium text-slate-700">Nama</label>
    <input id="name" name="name" type="text" value="{{ old('name', $supplier->name ?? '') }}" required class="{{ $input }}">
</div>
<div>
    <label for="phone" class="text-sm font-medium text-slate-700">Telepon</label>
    <input id="phone" name="phone" type="text" value="{{ old('phone', $supplier->phone ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label for="address" class="text-sm font-medium text-slate-700">Alamat</label>
    <textarea id="address" name="address" rows="3" class="{{ $input }}">{{ old('address', $supplier->address ?? '') }}</textarea>
</div>
<div>
    <label for="notes" class="text-sm font-medium text-slate-700">Catatan</label>
    <textarea id="notes" name="notes" rows="3" class="{{ $input }}">{{ old('notes', $supplier->notes ?? '') }}</textarea>
</div>
<div class="flex items-center gap-2">
    <input type="hidden" name="is_active" value="0">
    <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $supplier->is_active ?? true)) class="rounded border-slate-300 text-emerald-700">
    <label for="is_active" class="text-sm text-slate-700">Aktif</label>
</div>
