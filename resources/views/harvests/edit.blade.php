@extends('layouts.plantation')
@section('title', 'Ubah panen')
@section('content')
    <form action="{{ route('plantation.harvests.update', [$entity, $harvest]) }}" method="POST" class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('harvests._form')
        <div class="flex gap-3">
            <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">Simpan</button>
            <a href="{{ route('plantation.harvests.show', [$entity, $harvest]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Batal</a>
        </div>
    </form>
@endsection
