@extends('layouts.plantation')

@section('title', 'Tambah blok')

@section('content')
    <form action="{{ route('plantation.blocks.store', $entity) }}" method="POST" class="max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @include('blocks._form')
        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Simpan</button>
            <a href="{{ route('plantation.blocks.index', $entity) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700">Batal</a>
        </div>
    </form>
@endsection
