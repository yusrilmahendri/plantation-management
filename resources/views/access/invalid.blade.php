@extends('layouts.guest')

@section('title', 'Akses tidak valid')

@section('content')
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <p class="text-sm font-medium uppercase tracking-wide text-emerald-700">Manajemen Kebun</p>
            <h1 class="mt-3 text-2xl font-semibold text-slate-900">Akses tidak valid</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                Tautan akses ini tidak dapat digunakan. Tautan mungkin salah, kedaluwarsa, atau sudah dinonaktifkan.
            </p>
        </div>
    </div>
@endsection
