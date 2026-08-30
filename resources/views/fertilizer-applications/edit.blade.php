@extends('layouts.plantation')

@section('title', 'Ubah pemupukan')

@section('content')
    <form action="{{ route('plantation.fertilizer-applications.update', [$entity, $application]) }}" method="POST" class="max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('fertilizer-applications._form')
        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Simpan</button>
            <a href="{{ route('plantation.fertilizer-applications.show', [$entity, $application]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Batal</a>
        </div>
    </form>
@endsection
