@php
    $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';
    $usage = $usage ?? null;
    $oldItems = old('items');
    if ($oldItems === null && $usage) {
        $oldItems = $usage->items->map(fn ($line) => [
            'inventory_item_public_id' => $line->inventoryItem->public_id,
            'quantity' => $line->quantity,
            'notes' => $line->notes,
        ])->all();
    }
    $oldItems = $oldItems ?: [['inventory_item_public_id' => '', 'quantity' => '', 'notes' => '']];
@endphp

<div>
    <label class="text-sm font-medium text-slate-700">Tanggal</label>
    <input name="usage_date" type="date" required value="{{ old('usage_date', isset($usage) ? $usage->usage_date->toDateString() : now()->toDateString()) }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Kebun</label>
    <select id="plantation_public_id" name="plantation_public_id" required class="{{ $input }}">
        <option value="">Pilih kebun</option>
        @foreach ($plantations as $plantation)
            <option value="{{ $plantation->public_id }}" @selected(old('plantation_public_id', $usage->plantation->public_id ?? '') === $plantation->public_id)>{{ $plantation->name }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Blok (opsional, untuk herbisida/lokasi)</label>
    <select name="plantation_block_public_id" class="{{ $input }}">
        <option value="">Tanpa blok</option>
        @foreach ($blocks as $block)
            <option value="{{ $block['public_id'] }}" @selected(old('plantation_block_public_id', $usage->block->public_id ?? '') === $block['public_id'])>{{ $block['label'] }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Keterangan</label>
    <textarea name="description" rows="2" class="{{ $input }}">{{ old('description', $usage->description ?? '') }}</textarea>
</div>
<div class="sm:col-span-2">
    <p class="mb-2 text-sm font-medium text-slate-700">Barang dipakai</p>
    @foreach ($oldItems as $index => $line)
        <div class="mb-3 grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-3">
            <select name="items[{{ $index }}][inventory_item_public_id]" required class="{{ $input }}">
                <option value="">Pilih barang</option>
                @foreach ($inventoryItems as $item)
                    <option value="{{ $item->public_id }}" @selected(($line['inventory_item_public_id'] ?? '') === $item->public_id)>{{ $item->name }} ({{ $item->category->label() }})</option>
                @endforeach
            </select>
            <input name="items[{{ $index }}][quantity]" type="number" step="0.001" min="0.001" required placeholder="Qty" value="{{ $line['quantity'] ?? '' }}" class="{{ $input }}">
            <input name="items[{{ $index }}][notes]" type="text" placeholder="Catatan" value="{{ $line['notes'] ?? '' }}" class="{{ $input }}">
        </div>
    @endforeach
</div>
