@extends('layouts.plantation')
@section('title', 'Ubah pembeli')
@section('content')
    <form action="{{ route('plantation.buyers.update', [$entity, $buyer]) }}" method="POST" class="max-w-xl space-y-4 rounded-xl border bg-white p-6">
        @csrf
        @method('PUT')
        @include('buyers._form')
        <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">Simpan</button>
    </form>
@endsection
