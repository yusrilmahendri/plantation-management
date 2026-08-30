@php
    $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';
    $sale = $sale ?? null;
    $oldItems = old('items');
    if ($oldItems === null && $sale) {
        $oldItems = $sale->items->map(fn ($line) => [
            'harvest_public_id' => $line->harvest->public_id,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
        ])->all();
    }
    $oldItems = $oldItems ?: [['harvest_public_id' => '', 'quantity' => '', 'unit_price' => '']];
@endphp
<div>
    <label class="text-sm font-medium">Pembeli</label>
    <select name="buyer_public_id" required class="{{ $input }}">
        <option value="">Pilih pembeli</option>
        @foreach ($buyers as $buyer)
            <option value="{{ $buyer->public_id }}" @selected(old('buyer_public_id', $sale?->buyer?->public_id) === $buyer->public_id)>{{ $buyer->name }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium">Tanggal</label>
    <input name="sale_date" type="date" required value="{{ old('sale_date', $sale?->sale_date?->toDateString() ?? now()->toDateString()) }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium">Invoice</label>
    <input name="invoice_number" value="{{ old('invoice_number', $sale->invoice_number ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium">Penyesuaian</label>
    <input name="adjustment_amount" type="number" step="0.01" value="{{ old('adjustment_amount', $sale->adjustment_amount ?? '0') }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium">Keterangan</label>
    <textarea name="description" rows="2" class="{{ $input }}">{{ old('description', $sale->description ?? '') }}</textarea>
</div>
<div class="sm:col-span-2">
    <div class="mb-2 flex items-center justify-between">
        <p class="text-sm font-medium">Hasil panen</p>
        <button type="button" id="add-harvest-line" class="text-sm text-emerald-700">+ Tambah Hasil Panen</button>
    </div>
    <div id="sale-lines" class="space-y-3">
        @foreach ($oldItems as $index => $line)
            <div class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-3 sale-line">
                <select name="items[{{ $index }}][harvest_public_id]" required class="{{ $input }} harvest-select">
                    <option value="">Pilih panen</option>
                    @foreach ($harvestOptions as $option)
                        <option value="{{ $option['public_id'] }}" @selected(($line['harvest_public_id'] ?? '') === $option['public_id'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <input name="items[{{ $index }}][quantity]" type="number" step="0.001" min="0.001" required placeholder="Qty" value="{{ $line['quantity'] ?? '' }}" class="{{ $input }}">
                <input name="items[{{ $index }}][unit_price]" type="number" step="0.01" min="0" required placeholder="Harga satuan" value="{{ $line['unit_price'] ?? '' }}" class="{{ $input }}">
            </div>
        @endforeach
    </div>
</div>
<script>
    document.getElementById('add-harvest-line')?.addEventListener('click', () => {
        const wrap = document.getElementById('sale-lines');
        const first = wrap.querySelector('.sale-line');
        const clone = first.cloneNode(true);
        const index = wrap.querySelectorAll('.sale-line').length;
        clone.querySelectorAll('select, input').forEach((el) => {
            el.name = el.name.replace(/items\[\d+]/, 'items[' + index + ']');
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else el.value = '';
        });
        wrap.appendChild(clone);
    });
</script>
