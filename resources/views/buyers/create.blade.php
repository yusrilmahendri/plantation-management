@extends('layouts.plantation')
@section('title', 'Tambah pembeli')
@section('content')
    <form action="{{ route('plantation.buyers.store', $entity) }}" method="POST" class="max-w-xl space-y-4 rounded-xl border bg-white p-6">
        @csrf
        @include('buyers._form')
        <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">Simpan</button>
    </form>
@endsection
