<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Dasbor') — {{ $entity->name }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#f4f8fc] text-slate-800 antialiased">
        @php
            $nav = [
                ['icon' => 'DB', 'label' => 'Dashboard', 'route' => 'plantation.dashboard', 'active' => request()->routeIs('plantation.dashboard')],
                ['icon' => 'AG', 'label' => 'Anggaran', 'route' => 'plantation.budgets.index', 'active' => request()->routeIs('plantation.budgets.*')],
                ['icon' => 'AK', 'label' => 'Aktivitas Kerja', 'route' => 'plantation.work-activities.index', 'active' => request()->routeIs('plantation.work-activities.*')],
                ['icon' => 'KB', 'label' => 'Kebun', 'route' => 'plantation.plantations.index', 'active' => request()->routeIs('plantation.plantations.*')],
                ['icon' => 'BL', 'label' => 'Blok', 'route' => 'plantation.blocks.index', 'active' => request()->routeIs('plantation.blocks.*')],
                ['icon' => 'PK', 'label' => 'Pekerja', 'route' => 'plantation.workers.index', 'active' => request()->routeIs('plantation.workers.*')],
                ['icon' => 'JP', 'label' => 'Jenis Pekerjaan', 'route' => 'plantation.work-types.index', 'active' => request()->routeIs('plantation.work-types.*')],
                ['icon' => 'SP', 'label' => 'Supplier', 'route' => 'plantation.suppliers.index', 'active' => request()->routeIs('plantation.suppliers.*')],
                ['icon' => 'IV', 'label' => 'Inventory', 'route' => 'plantation.inventory-items.index', 'active' => request()->routeIs('plantation.inventory-items.*') || request()->routeIs('plantation.stock-adjustments.*')],
                ['icon' => 'PB', 'label' => 'Pembelian', 'route' => 'plantation.purchases.index', 'active' => request()->routeIs('plantation.purchases.*')],
                ['icon' => 'PS', 'label' => 'Pemakaian Stok', 'route' => 'plantation.material-usages.index', 'active' => request()->routeIs('plantation.material-usages.*')],
                ['icon' => 'PM', 'label' => 'Pemupukan', 'route' => 'plantation.fertilizer-applications.index', 'active' => request()->routeIs('plantation.fertilizer-applications.*')],
                ['icon' => 'PN', 'label' => 'Panen', 'route' => 'plantation.harvests.index', 'active' => request()->routeIs('plantation.harvests.*')],
                ['icon' => 'PJ', 'label' => 'Penjualan', 'route' => 'plantation.harvest-sales.index', 'active' => request()->routeIs('plantation.harvest-sales.*')],
                ['icon' => 'BY', 'label' => 'Pembeli', 'route' => 'plantation.buyers.index', 'active' => request()->routeIs('plantation.buyers.*')],
                ['icon' => 'LP', 'label' => 'Laporan Produksi', 'route' => 'plantation.production-reports.show', 'active' => request()->routeIs('plantation.production-reports.*')],
                ['icon' => 'FN', 'label' => 'Integrasi Finance', 'route' => 'plantation.integration.show', 'active' => request()->routeIs('plantation.integration.*')],
            ];
        @endphp

        <div class="plantation-shell lg:flex">
            <aside id="sidebar" class="plantation-sidebar fixed left-0 top-0 bottom-0 z-50 w-[17.5rem] -translate-x-full border-r border-slate-200/80 bg-white transition-transform duration-200 lg:sticky lg:top-0 lg:translate-x-0">
                <div class="flex h-20 shrink-0 items-center gap-3 border-b border-slate-200/80 px-5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-sm font-bold text-blue-700 ring-1 ring-blue-100">MK</div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Manajemen Kebun</p>
                        <p class="truncate text-sm font-semibold text-slate-950">{{ $entity->name }}</p>
                    </div>
                </div>
                <nav class="plantation-sidebar-nav space-y-1 p-3">
                    @foreach ($nav as $item)
                        <a
                            href="{{ route($item['route'], $entity) }}"
                            class="nav-item {{ $item['active'] ? 'is-active' : '' }}"
                        >
                            <span class="nav-icon" aria-hidden="true">{{ $item['icon'] }}</span>
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </aside>

            <div class="flex min-h-screen min-w-0 flex-1 flex-col lg:pl-0">
                <header class="topbar sticky top-0 z-30 flex min-h-16 items-center justify-between gap-3 px-4 py-3 text-white shadow-sm lg:px-8">
                    <button id="sidebar-toggle" type="button" class="topbar-button lg:hidden" aria-controls="sidebar" aria-expanded="false">
                        Menu
                    </button>
                    <div class="min-w-0 flex-1">
                        <div class="flex min-w-0 items-center gap-2">
                            <h1 class="truncate text-base font-semibold">@yield('title', 'Dasbor')</h1>
                            <span class="business-badge">BUSINESS</span>
                        </div>
                        <p class="truncate text-xs text-blue-100">{{ $entity->name }}</p>
                    </div>
                    <span class="hidden shrink-0 rounded-full bg-white/15 px-3 py-1.5 text-xs font-medium text-blue-50 ring-1 ring-white/20 sm:inline">Akses tautan privat</span>
                </header>

                <main class="page-main flex-1 px-4 py-6 lg:px-8">
                    @if (session('success'))
                        <div class="alert alert-success mb-4">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger mb-4">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger mb-4">
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

                <footer class="px-4 py-5 text-center text-xs text-slate-500 lg:px-8">
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
