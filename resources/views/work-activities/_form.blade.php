@php
    $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';
    $activity = $activity ?? null;
@endphp

<div>
    <label class="text-sm font-medium text-slate-700">Judul</label>
    <input name="title" type="text" required value="{{ old('title', $activity->title ?? '') }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Tanggal</label>
    <input name="activity_date" type="date" required value="{{ old('activity_date', isset($activity) ? $activity->activity_date->toDateString() : now()->toDateString()) }}" class="{{ $input }}">
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Kebun</label>
    <select id="plantation_public_id" name="plantation_public_id" required class="{{ $input }}">
        <option value="">Pilih kebun</option>
        @foreach ($plantations as $plantation)
            <option value="{{ $plantation->public_id }}" @selected(old('plantation_public_id', $activity->plantation->public_id ?? '') === $plantation->public_id)>{{ $plantation->name }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Blok (opsional)</label>
    <select id="plantation_block_public_id" name="plantation_block_public_id" class="{{ $input }}">
        <option value="">Tanpa blok</option>
        @foreach ($blocks as $block)
            <option value="{{ $block['public_id'] }}" data-plantation="{{ $block['plantation_public_id'] }}" @selected(old('plantation_block_public_id', $activity->block->public_id ?? '') === $block['public_id'])>{{ $block['label'] }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Jenis pekerjaan</label>
    <select name="work_type_public_id" required class="{{ $input }}">
        <option value="">Pilih jenis</option>
        @foreach ($workTypes as $workType)
            <option value="{{ $workType->public_id }}" @selected(old('work_type_public_id', $activity->workType->public_id ?? '') === $workType->public_id)>{{ $workType->name }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Status</label>
    <select name="status" class="{{ $input }}">
        @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected(old('status', $activity->status->value ?? 'DRAFT') === $status->value)>{{ $status->label() }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Deskripsi</label>
    <textarea name="description" rows="3" class="{{ $input }}">{{ old('description', $activity->description ?? '') }}</textarea>
</div>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="text-sm font-medium text-slate-700">Mulai</label>
        <input name="started_at" type="datetime-local" value="{{ old('started_at', isset($activity) && $activity->started_at ? $activity->started_at->format('Y-m-d\\TH:i') : '') }}" class="{{ $input }}">
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Selesai</label>
        <input name="finished_at" type="datetime-local" value="{{ old('finished_at', isset($activity) && $activity->finished_at ? $activity->finished_at->format('Y-m-d\\TH:i') : '') }}" class="{{ $input }}">
    </div>
</div>
