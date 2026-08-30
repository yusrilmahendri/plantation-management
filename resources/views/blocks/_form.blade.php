@php
    $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';
@endphp

<div>
    <label for="plantation_public_id" class="text-sm font-medium text-slate-700">Kebun</label>
    <select id="plantation_public_id" name="plantation_public_id" required class="{{ $input }}">
        <option value="">Pilih kebun</option>
        @foreach ($plantations as $plantation)
            <option value="{{ $plantation->public_id }}" @selected(old('plantation_public_id', isset($block) ? $block->plantation?->public_id : '') === $plantation->public_id)>
                {{ $plantation->name }}
            </option>
        @endforeach
    </select>
</div>
<div>
    <label for="code" class="text-sm font-medium text-slate-700">Kode</label>
    <input id="code" name="code" type="text" value="{{ old('code', $block->code ?? '') }}" required class="{{ $input }}">
</div>
<div>
    <label for="name" class="text-sm font-medium text-slate-700">Nama</label>
    <input id="name" name="name" type="text" value="{{ old('name', $block->name ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label for="area" class="text-sm font-medium text-slate-700">Luas</label>
    <input id="area" name="area" type="number" step="0.01" min="0" value="{{ old('area', $block->area ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label for="crop_type" class="text-sm font-medium text-slate-700">Jenis tanaman</label>
    <input id="crop_type" name="crop_type" type="text" value="{{ old('crop_type', $block->crop_type ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label for="planting_year" class="text-sm font-medium text-slate-700">Tahun tanam</label>
    <input id="planting_year" name="planting_year" type="number" min="1900" max="{{ now()->year + 5 }}" value="{{ old('planting_year', $block->planting_year ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label for="notes" class="text-sm font-medium text-slate-700">Catatan</label>
    <textarea id="notes" name="notes" rows="3" class="{{ $input }}">{{ old('notes', $block->notes ?? '') }}</textarea>
</div>
<div class="flex items-center gap-2">
    <input type="hidden" name="is_active" value="0">
    <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $block->is_active ?? true)) class="rounded border-slate-300 text-emerald-700">
    <label for="is_active" class="text-sm text-slate-700">Aktif</label>
</div>
