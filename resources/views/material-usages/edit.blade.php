@extends('layouts.plantation')

@section('title', 'Ubah pemakaian')

@section('content')
    <form action="{{ route('plantation.material-usages.update', [$entity, $usage]) }}" method="POST" class="max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('material-usages._form')
        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Simpan</button>
            <a href="{{ route('plantation.material-usages.show', [$entity, $usage]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Batal</a>
        </div>
    </form>
@endsection
