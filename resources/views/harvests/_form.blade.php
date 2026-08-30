@php
    $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';
    $harvest = $harvest ?? null;
    $activity = $activity ?? null;
@endphp
<div>
    <label class="text-sm font-medium">Tanggal</label>
    <input name="harvest_date" type="date" required value="{{ old('harvest_date', $harvest?->harvest_date?->toDateString() ?? $activity?->activity_date?->toDateString() ?? now()->toDateString()) }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium">Kebun</label>
    <select name="plantation_public_id" required class="{{ $input }}">
        <option value="">Pilih kebun</option>
        @foreach ($plantations as $plantation)
            <option value="{{ $plantation->public_id }}" @selected(old('plantation_public_id', $harvest?->plantation?->public_id ?? $activity?->plantation?->public_id) === $plantation->public_id)>{{ $plantation->name }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium">Blok</label>
    <select name="plantation_block_public_id" class="{{ $input }}">
        <option value="">Tanpa blok</option>
        @foreach ($blocks as $block)
            <option value="{{ $block['public_id'] }}" @selected(old('plantation_block_public_id', $harvest?->block?->public_id ?? $activity?->block?->public_id) === $block['public_id'])>{{ $block['label'] }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium">Aktivitas kerja</label>
    <select name="work_activity_public_id" class="{{ $input }}">
        <option value="">Tanpa aktivitas</option>
        @foreach ($activities as $row)
            <option value="{{ $row->public_id }}" @selected(old('work_activity_public_id', $harvest?->workActivity?->public_id ?? $activity?->public_id) === $row->public_id)>{{ $row->title }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium">Komoditas</label>
    <select name="commodity" required class="{{ $input }}">
        @foreach ($commodities as $commodity)
            <option value="{{ $commodity->value }}" @selected(old('commodity', $harvest?->commodity?->value) === $commodity->value)>{{ $commodity->label() }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium">Kuantitas</label>
    <input name="quantity" type="number" step="0.001" min="0.001" required value="{{ old('quantity', $harvest->quantity ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium">Satuan</label>
    <input name="unit" required value="{{ old('unit', $harvest->unit ?? 'kg') }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium">Jumlah tandan</label>
    <input name="bunch_count" type="number" min="0" value="{{ old('bunch_count', $harvest->bunch_count ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium">Mutu</label>
    <input name="quality_grade" value="{{ old('quality_grade', $harvest->quality_grade ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium">Catatan</label>
    <textarea name="notes" rows="2" class="{{ $input }}">{{ old('notes', $harvest->notes ?? '') }}</textarea>
</div>
