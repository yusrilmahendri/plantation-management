@extends('layouts.plantation')
@section('title', 'Ubah penjualan')
@section('content')
    <form action="{{ route('plantation.harvest-sales.update', [$entity, $sale]) }}" method="POST" class="max-w-3xl space-y-4 rounded-xl border bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('harvest-sales._form')
        <div class="flex gap-3">
            <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white">Simpan</button>
            <a href="{{ route('plantation.harvest-sales.show', [$entity, $sale]) }}" class="rounded-lg border px-4 py-2 text-sm">Batal</a>
        </div>
    </form>
@endsection
