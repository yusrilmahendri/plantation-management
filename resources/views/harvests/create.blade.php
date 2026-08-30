@extends('layouts.plantation')
@section('title', 'Tambah panen')
@section('content')
    <form action="{{ route('plantation.harvests.store', $entity) }}" method="POST" class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @include('harvests._form')
        <div class="flex gap-3">
            <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">Simpan draft</button>
            <a href="{{ route('plantation.harvests.index', $entity) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Batal</a>
        </div>
    </form>
@endsection
