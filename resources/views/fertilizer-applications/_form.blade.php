@php
    $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';
    $application = $application ?? null;
    $oldItems = old('items');
    if ($oldItems === null && $application) {
        $oldItems = $application->items->map(fn ($line) => [
            'inventory_item_public_id' => $line->inventoryItem->public_id,
            'quantity' => $line->quantity,
            'dosage_per_plant' => $line->dosage_per_plant,
            'plant_count' => $line->plant_count,
            'notes' => $line->notes,
        ])->all();
    }
    $oldItems = $oldItems ?: [['inventory_item_public_id' => '', 'quantity' => '', 'dosage_per_plant' => '', 'plant_count' => '', 'notes' => '']];
@endphp

<div>
    <label class="text-sm font-medium text-slate-700">Tanggal</label>
    <input name="application_date" type="date" required value="{{ old('application_date', isset($application) ? $application->application_date->toDateString() : now()->toDateString()) }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Kebun</label>
    <select name="plantation_public_id" required class="{{ $input }}">
        <option value="">Pilih kebun</option>
        @foreach ($plantations as $plantation)
            <option value="{{ $plantation->public_id }}" @selected(old('plantation_public_id', $application->plantation->public_id ?? '') === $plantation->public_id)>{{ $plantation->name }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Blok</label>
    <select name="plantation_block_public_id" required class="{{ $input }}">
        <option value="">Pilih blok</option>
        @foreach ($blocks as $block)
            <option value="{{ $block['public_id'] }}" @selected(old('plantation_block_public_id', $application->block->public_id ?? '') === $block['public_id'])>{{ $block['label'] }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Aktivitas kerja (opsional)</label>
    <select name="work_activity_public_id" class="{{ $input }}">
        <option value="">Tanpa aktivitas</option>
        @foreach ($activities as $activity)
            <option value="{{ $activity->public_id }}" @selected(old('work_activity_public_id', $application->workActivity->public_id ?? '') === $activity->public_id)>{{ $activity->title }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Catatan</label>
    <textarea name="notes" rows="2" class="{{ $input }}">{{ old('notes', $application->notes ?? '') }}</textarea>
</div>
<div class="sm:col-span-2">
    <p class="mb-2 text-sm font-medium text-slate-700">Pupuk</p>
    @foreach ($oldItems as $index => $line)
        <div class="mb-3 grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-2">
            <select name="items[{{ $index }}][inventory_item_public_id]" required class="{{ $input }}">
                <option value="">Pilih pupuk</option>
                @foreach ($inventoryItems as $item)
                    <option value="{{ $item->public_id }}" @selected(($line['inventory_item_public_id'] ?? '') === $item->public_id)>{{ $item->name }}</option>
                @endforeach
            </select>
            <input name="items[{{ $index }}][quantity]" type="number" step="0.001" min="0.001" required placeholder="Qty" value="{{ $line['quantity'] ?? '' }}" class="{{ $input }}">
            <input name="items[{{ $index }}][dosage_per_plant]" type="number" step="0.001" placeholder="Dosis/tanaman" value="{{ $line['dosage_per_plant'] ?? '' }}" class="{{ $input }}">
            <input name="items[{{ $index }}][plant_count]" type="number" min="1" placeholder="Jumlah tanaman" value="{{ $line['plant_count'] ?? '' }}" class="{{ $input }}">
        </div>
    @endforeach
</div>
