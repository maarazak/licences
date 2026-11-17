<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-50 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Administration' }} — Licences</title>
    
    <link rel="icon" type="image/svg+xml" href="/icon.svg?v=2">
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="shortcut icon" href="/favicon.ico?v=2">
    <link rel="apple-touch-icon" href="/icon.svg?v=2">
    
    <!-- Tailwind CSS CDN pour affichage instantané et fiable -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#0f766e',
                            light: '#14b8a6',
                            dark: '#115e59',
                            soft: '#ccfbf1',
                        }
                    }
                }
            }
        }
    </script>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full font-sans text-slate-800 flex">

    <!-- Sidebar Navigation -->
    <aside class="w-64 bg-slate-900 text-slate-200 flex flex-col shrink-0">
        <div class="h-16 flex items-center px-6 border-b border-slate-800 gap-3">
            <svg width="32" height="32" viewBox="0 0 48 48" aria-hidden="true" class="shrink-0 shadow-sm rounded-lg">
                <path d="M12 0H36A12 12 0 0 1 48 12V19.5A4.5 4.5 0 0 0 48 28.5V36A12 12 0 0 1 36 48H12A12 12 0 0 1 0 36V28.5A4.5 4.5 0 0 0 0 19.5V12A12 12 0 0 1 12 0Z" fill="#0d5c4a"/>
                <path d="M16 12H23V29.5H33V36H16Z" fill="#ffffff"/>
                <circle cx="29.5" cy="15.5" r="3.5" fill="#e8590c"/>
            </svg>
            <div class="flex items-center">
                <span class="font-bold text-white tracking-wide text-base">Licences</span>
                <span class="text-[10px] px-2 py-0.5 ml-2 rounded-full bg-teal-900/80 text-teal-300 font-semibold border border-teal-700/50">Admin</span>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.dashboard') ? 'bg-teal-600 text-white shadow' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                <i data-lucide="layout-dashboard" class="size-4"></i>
                Tableau de bord
            </a>

            <a href="{{ route('admin.orders.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.orders.*') ? 'bg-teal-600 text-white shadow' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                <i data-lucide="shopping-bag" class="size-4"></i>
                Commandes
            </a>

            <a href="{{ route('admin.products.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.products.*') ? 'bg-teal-600 text-white shadow' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                <i data-lucide="package" class="size-4"></i>
                Produits
            </a>

            <a href="{{ route('admin.clients.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.clients.*') ? 'bg-teal-600 text-white shadow' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                <i data-lucide="users" class="size-4"></i>
                Clients
            </a>

            <a href="{{ route('admin.tickets.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.tickets.*') ? 'bg-teal-600 text-white shadow' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                <i data-lucide="life-buoy" class="size-4"></i>
                Assistance
            </a>

            <a href="{{ route('admin.journal.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.journal.*') ? 'bg-teal-600 text-white shadow' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                <i data-lucide="activity" class="size-4"></i>
                Journal d'audit
            </a>

            <a href="{{ route('admin.settings.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.settings.*') ? 'bg-teal-600 text-white shadow' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                <i data-lucide="settings" class="size-4"></i>
                Paramètres
            </a>
        </nav>

        <div class="p-4 border-t border-slate-800">
            <div class="flex items-center justify-between">
                <div class="truncate mr-2">
                    <p class="text-xs font-semibold text-white truncate">{{ auth('admin')->user()->email ?? 'Administrateur' }}</p>
                    <p class="text-[11px] text-teal-400">Rôle : Admin (Spatie)</p>
                </div>
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" title="Déconnexion" class="p-2 text-slate-400 hover:text-red-400 hover:bg-slate-800 rounded-lg transition">
                        <i data-lucide="log-out" class="size-4"></i>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <!-- Top Bar -->
        <header class="h-16 bg-white border-b border-slate-200 px-8 flex items-center justify-between shrink-0 sticky top-0 z-10">
            <h1 class="text-lg font-bold text-slate-800">{{ $header ?? 'Tableau de bord' }}</h1>
            <div class="flex items-center gap-4">
                <a href="{{ route('home') }}" target="_blank" class="flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-teal-700 bg-slate-100 hover:bg-teal-50 px-3 py-1.5 rounded-lg border border-slate-200 transition">
                    <i data-lucide="external-link" class="size-3.5"></i>
                    Voir la boutique (Vue 3)
                </a>
            </div>
        </header>

        <!-- Body -->
        <main class="p-8 flex-1">
            @yield('content')
        </main>
    </div>

    <!-- Scripts Initialization -->
    <script>
        lucide.createIcons();

        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Opération réussie',
                text: '{{ session('success') }}',
                timer: 3500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '{{ session('error') }}'
            });
        @endif

        function confirmAction(options) {
            Swal.fire({
                title: options.title || 'Êtes-vous sûr ?',
                text: options.text || 'Cette action est irréversible.',
                icon: options.icon || 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0f766e',
                cancelButtonColor: '#64748b',
                confirmButtonText: options.confirmText || 'Confirmer',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed && options.formId) {
                    document.getElementById(options.formId).submit();
                } else if (result.isConfirmed && typeof options.onConfirm === 'function') {
                    options.onConfirm();
                }
            });
        }
    </script>
    @stack('scripts')
</body>
</html>
