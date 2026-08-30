@extends('layouts.plantation')

@section('title', 'Ubah pembelian')

@section('content')
    <form action="{{ route('plantation.purchases.update', [$entity, $purchase]) }}" method="POST" class="max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('purchases._form')
        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Simpan</button>
            <a href="{{ route('plantation.purchases.show', [$entity, $purchase]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700">Batal</a>
        </div>
    </form>
@endsection
