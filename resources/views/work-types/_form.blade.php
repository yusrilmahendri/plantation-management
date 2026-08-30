@php
    $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';
@endphp

<div>
    <label for="name" class="text-sm font-medium text-slate-700">Nama</label>
    <input id="name" name="name" type="text" value="{{ old('name', $workType->name ?? '') }}" required class="{{ $input }}">
</div>
<div>
    <label for="description" class="text-sm font-medium text-slate-700">Deskripsi</label>
    <textarea id="description" name="description" rows="3" class="{{ $input }}">{{ old('description', $workType->description ?? '') }}</textarea>
</div>
<div>
    <label for="default_rate" class="text-sm font-medium text-slate-700">Tarif default</label>
    <input id="default_rate" name="default_rate" type="number" step="0.01" min="0" value="{{ old('default_rate', $workType->default_rate ?? '') }}" class="{{ $input }}">
</div>
<div class="flex items-center gap-2">
    <input type="hidden" name="is_active" value="0">
    <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $workType->is_active ?? true)) class="rounded border-slate-300 text-emerald-700">
    <label for="is_active" class="text-sm text-slate-700">Aktif</label>
</div>
