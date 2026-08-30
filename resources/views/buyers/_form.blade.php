@php $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm'; $buyer = $buyer ?? null; @endphp
<div>
    <label class="text-sm font-medium">Nama</label>
    <input name="name" required value="{{ old('name', $buyer->name ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium">Telepon</label>
    <input name="phone" value="{{ old('phone', $buyer->phone ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium">Kontak</label>
    <input name="contact_person" value="{{ old('contact_person', $buyer->contact_person ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium">Alamat</label>
    <textarea name="address" rows="2" class="{{ $input }}">{{ old('address', $buyer->address ?? '') }}</textarea>
</div>
<div>
    <label class="text-sm font-medium">Catatan</label>
    <textarea name="notes" rows="2" class="{{ $input }}">{{ old('notes', $buyer->notes ?? '') }}</textarea>
</div>
<div class="flex items-center gap-2">
    <input type="hidden" name="is_active" value="0">
    <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $buyer->is_active ?? true)) class="rounded border-slate-300 text-emerald-700">
    <label for="is_active" class="text-sm">Aktif</label>
</div>
