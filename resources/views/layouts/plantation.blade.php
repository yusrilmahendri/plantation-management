<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Dasbor') — {{ $entity->name }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
        @php
            $nav = [
                ['label' => 'Dashboard', 'route' => 'plantation.dashboard', 'active' => request()->routeIs('plantation.dashboard')],
                ['label' => 'Anggaran', 'route' => 'plantation.budgets.index', 'active' => request()->routeIs('plantation.budgets.*')],
                ['label' => 'Aktivitas Kerja', 'route' => 'plantation.work-activities.index', 'active' => request()->routeIs('plantation.work-activities.*')],
                ['label' => 'Kebun', 'route' => 'plantation.plantations.index', 'active' => request()->routeIs('plantation.plantations.*')],
                ['label' => 'Blok', 'route' => 'plantation.blocks.index', 'active' => request()->routeIs('plantation.blocks.*')],
                ['label' => 'Pekerja', 'route' => 'plantation.workers.index', 'active' => request()->routeIs('plantation.workers.*')],
                ['label' => 'Jenis Pekerjaan', 'route' => 'plantation.work-types.index', 'active' => request()->routeIs('plantation.work-types.*')],
                ['label' => 'Supplier', 'route' => 'plantation.suppliers.index', 'active' => request()->routeIs('plantation.suppliers.*')],
                ['label' => 'Inventory', 'route' => 'plantation.inventory-items.index', 'active' => request()->routeIs('plantation.inventory-items.*') || request()->routeIs('plantation.stock-adjustments.*')],
                ['label' => 'Pembelian', 'route' => 'plantation.purchases.index', 'active' => request()->routeIs('plantation.purchases.*')],
                ['label' => 'Pemakaian Stok', 'route' => 'plantation.material-usages.index', 'active' => request()->routeIs('plantation.material-usages.*')],
                ['label' => 'Pemupukan', 'route' => 'plantation.fertilizer-applications.index', 'active' => request()->routeIs('plantation.fertilizer-applications.*')],
                ['label' => 'Panen', 'route' => 'plantation.harvests.index', 'active' => request()->routeIs('plantation.harvests.*')],
                ['label' => 'Penjualan', 'route' => 'plantation.harvest-sales.index', 'active' => request()->routeIs('plantation.harvest-sales.*')],
                ['label' => 'Pembeli', 'route' => 'plantation.buyers.index', 'active' => request()->routeIs('plantation.buyers.*')],
                ['label' => 'Laporan Produksi', 'route' => 'plantation.production-reports.show', 'active' => request()->routeIs('plantation.production-reports.*')],
                ['label' => 'Integrasi Finance', 'route' => 'plantation.integration.show', 'active' => request()->routeIs('plantation.integration.*')],
            ];
        @endphp

        <div class="plantation-shell lg:flex">
            <aside id="sidebar" class="plantation-sidebar fixed left-0 top-0 bottom-0 z-50 w-64 -translate-x-full border-r border-slate-200 bg-white transition-transform lg:sticky lg:top-0 lg:translate-x-0">
                <div class="flex h-16 shrink-0 items-center border-b border-slate-200 px-5">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Manajemen Kebun</p>
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $entity->name }}</p>
                    </div>
                </div>
                <nav class="plantation-sidebar-nav space-y-1 p-3">
                    @foreach ($nav as $item)
                        <a
                            href="{{ route($item['route'], $entity) }}"
                            class="block min-h-11 rounded-lg px-3 py-2.5 text-sm font-medium {{ $item['active'] ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
                        >
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </aside>

            <div class="flex min-h-screen min-w-0 flex-1 flex-col lg:pl-0">
                <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 lg:px-8">
                    <button id="sidebar-toggle" type="button" class="min-h-11 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 lg:hidden" aria-controls="sidebar" aria-expanded="false">
                        Menu
                    </button>
                    <h1 class="min-w-0 truncate px-3 text-base font-semibold text-slate-900">@yield('title', 'Dasbor')</h1>
                    <span class="hidden text-xs text-slate-500 sm:inline">Akses tautan privat</span>
                </header>

                <main class="flex-1 px-4 py-6 lg:px-8">
                    @if (session('success'))
                        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <p class="font-medium">Periksa kembali isian berikut:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </main>

                <footer class="border-t border-slate-200 px-4 py-4 text-center text-xs text-slate-500 lg:px-8">
                    Created by @Yusril Mahendri
                </footer>
            </div>
        </div>

        <div id="sidebar-overlay" class="fixed inset-0 z-40 hidden bg-slate-900/40 lg:hidden"></div>

        <script>
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const toggle = document.getElementById('sidebar-toggle');

            const setSidebarOpen = (open) => {
                sidebar?.classList.toggle('-translate-x-full', !open);
                overlay?.classList.toggle('hidden', !open);
                document.body.classList.toggle('sidebar-open', open);
                toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
            };

            const closeSidebar = () => {
                setSidebarOpen(false);
            };

            toggle?.addEventListener('click', () => {
                setSidebarOpen(sidebar.classList.contains('-translate-x-full'));
            });

            overlay?.addEventListener('click', closeSidebar);

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeSidebar();
                }
            });
        </script>
    </body>
</html>
