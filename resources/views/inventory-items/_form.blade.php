@php
    $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';
@endphp

<div>
    <label for="name" class="text-sm font-medium text-slate-700">Nama</label>
    <input id="name" name="name" type="text" value="{{ old('name', $item->name ?? '') }}" required class="{{ $input }}">
</div>
<div>
    <label for="sku" class="text-sm font-medium text-slate-700">SKU</label>
    <input id="sku" name="sku" type="text" value="{{ old('sku', $item->sku ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label for="category" class="text-sm font-medium text-slate-700">Kategori</label>
    <select id="category" name="category" required class="{{ $input }}">
        <option value="">Pilih kategori</option>
        @foreach (\App\Enums\InventoryCategory::cases() as $category)
            <option value="{{ $category->value }}" @selected(old('category', isset($item) ? $item->category->value : '') === $category->value)>
                {{ $category->label() }}
            </option>
        @endforeach
    </select>
</div>
<div>
    <label for="unit" class="text-sm font-medium text-slate-700">Satuan</label>
    <input id="unit" name="unit" type="text" value="{{ old('unit', $item->unit ?? '') }}" required class="{{ $input }}">
</div>
<div>
    <label for="minimum_stock" class="text-sm font-medium text-slate-700">Stok minimum</label>
    <input id="minimum_stock" name="minimum_stock" type="number" step="0.01" min="0" value="{{ old('minimum_stock', $item->minimum_stock ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label for="description" class="text-sm font-medium text-slate-700">Deskripsi</label>
    <textarea id="description" name="description" rows="3" class="{{ $input }}">{{ old('description', $item->description ?? '') }}</textarea>
</div>
<div class="flex items-center gap-2">
    <input type="hidden" name="is_active" value="0">
    <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $item->is_active ?? true)) class="rounded border-slate-300 text-emerald-700">
    <label for="is_active" class="text-sm text-slate-700">Aktif</label>
</div>
