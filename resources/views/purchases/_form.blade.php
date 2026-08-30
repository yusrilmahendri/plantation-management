@php
    $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';
    $purchase = $purchase ?? null;
    $oldItems = old('items');
    if ($oldItems === null && $purchase) {
        $oldItems = $purchase->items->map(fn ($line) => [
            'inventory_item_public_id' => $line->inventoryItem->public_id,
            'quantity' => $line->quantity,
            'unit_cost' => $line->unit_cost,
        ])->all();
    }
    $oldItems = $oldItems ?: [['inventory_item_public_id' => '', 'quantity' => '', 'unit_cost' => '']];
@endphp

<div>
    <label class="text-sm font-medium text-slate-700">Tanggal</label>
    <input name="purchase_date" type="date" required value="{{ old('purchase_date', isset($purchase) ? $purchase->purchase_date->toDateString() : now()->toDateString()) }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Supplier</label>
    <select name="supplier_public_id" class="{{ $input }}">
        <option value="">Tanpa supplier</option>
        @foreach ($suppliers as $supplier)
            <option value="{{ $supplier->public_id }}" @selected(old('supplier_public_id', $purchase?->supplier?->public_id) === $supplier->public_id)>{{ $supplier->name }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Nomor invoice</label>
    <input name="invoice_number" type="text" value="{{ old('invoice_number', $purchase->invoice_number ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Penyesuaian</label>
    <input name="adjustment_amount" type="number" step="0.01" value="{{ old('adjustment_amount', $purchase->adjustment_amount ?? '0') }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Item anggaran (opsional)</label>
    <select name="budget_allocation_item_public_id" class="{{ $input }}">
        <option value="">Tanpa realisasi anggaran</option>
        @foreach ($budgetItems as $budgetItem)
            <option value="{{ $budgetItem->public_id }}" @selected(old('budget_allocation_item_public_id', $purchase?->budgetAllocationItem?->public_id) === $budgetItem->public_id)>
                {{ $budgetItem->name }} ({{ $budgetItem->category->label() }})
            </option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Keterangan</label>
    <textarea name="description" rows="2" class="{{ $input }}">{{ old('description', $purchase->description ?? '') }}</textarea>
</div>

<div class="sm:col-span-2">
    <p class="mb-2 text-sm font-medium text-slate-700">Item pembelian</p>
    <div class="space-y-3">
        @foreach ($oldItems as $index => $line)
            <div class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-3">
                <select name="items[{{ $index }}][inventory_item_public_id]" required class="{{ $input }}">
                    <option value="">Pilih barang</option>
                    @foreach ($inventoryItems as $item)
                        <option value="{{ $item->public_id }}" @selected(($line['inventory_item_public_id'] ?? '') === $item->public_id)>{{ $item->name }}</option>
                    @endforeach
                </select>
                <input name="items[{{ $index }}][quantity]" type="number" step="0.001" min="0.001" required placeholder="Qty" value="{{ $line['quantity'] ?? '' }}" class="{{ $input }}">
                <input name="items[{{ $index }}][unit_cost]" type="number" step="0.01" min="0" required placeholder="Harga satuan" value="{{ $line['unit_cost'] ?? '' }}" class="{{ $input }}">
            </div>
        @endforeach
    </div>
</div>
