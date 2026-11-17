<!DOCTYPE html>
<html lang="fr" class="h-full bg-[#f4f6fb] antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Administration' }} — Licences</title>
    
    <link rel="icon" type="image/svg+xml" href="/icon.svg?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png?v=3">
    <link rel="shortcut icon" href="/favicon.ico?v=3">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ink: '#0b1220',
                        muted: '#5b6475',
                        line: '#e6e9f1',
                        paper: '#ffffff',
                        canvas: '#f4f6fb',
                        brand: {
                            DEFAULT: '#0d5c4a',
                            strong: '#083f33',
                            bright: '#19c39a',
                            soft: '#e4f7f1',
                        },
                        accent: {
                            DEFAULT: '#ff6a13',
                            strong: '#e85a07',
                            soft: '#fff0e5',
                            ink: '#b8400a',
                        },
                        success: {
                            DEFAULT: '#0f7a4f',
                            soft: '#dcf7e9',
                        },
                        warning: {
                            DEFAULT: '#8a5a00',
                            soft: '#fff1cf',
                        },
                        danger: {
                            DEFAULT: '#c0271b',
                            soft: '#fde7e4',
                        },
                        info: {
                            DEFAULT: '#1d4f9c',
                            soft: '#e3edfc',
                        },
                    },
                    boxShadow: {
                        card: '0 1px 2px rgb(11 18 32 / 0.04), 0 10px 30px -12px rgb(11 18 32 / 0.12)',
                        pop: '0 2px 4px rgb(11 18 32 / 0.04), 0 24px 48px -16px rgb(13 92 74 / 0.35)',
                    }
                }
            }
        }
    </script>
    <style>
        .h1 { font-size: 1.875rem; line-height: 2.25rem; font-weight: 800; letter-spacing: -0.025em; color: #0b1220; }
        @media (min-width: 640px) { .h1 { font-size: 2.25rem; line-height: 2.5rem; } }
        .h2 { font-size: 1.5rem; line-height: 2rem; font-weight: 800; letter-spacing: -0.025em; color: #0b1220; }
        @media (min-width: 640px) { .h2 { font-size: 1.875rem; line-height: 2.25rem; } }
        .h3 { font-size: 1rem; line-height: 1.5rem; font-weight: 700; color: #0b1220; }
        .lead { font-size: 1rem; line-height: 1.625; color: #5b6475; }
        .card { border-radius: 1rem; border: 1px solid #e6e9f1; background-color: #ffffff; box-shadow: 0 1px 2px rgb(11 18 32 / 0.04), 0 10px 30px -12px rgb(11 18 32 / 0.12); }
        .btn { display: inline-flex; min-height: 2.75rem; cursor: pointer; align-items: center; justify-content: center; gap: 0.5rem; border-radius: 0.75rem; padding-left: 1.25rem; padding-right: 1.25rem; font-size: 0.875rem; font-weight: 700; transition: all 0.2s; white-space: nowrap; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-brand { background: linear-gradient(to bottom, #117a62, #0d5c4a); color: #ffffff; box-shadow: 0 10px 24px -10px rgb(13 92 74 / 0.7); }
        .btn-brand:hover { transform: translateY(-1px); filter: brightness(1.05); }
        .btn-soft { border: 1px solid #e6e9f1; background-color: #ffffff; color: #0b1220; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .btn-soft:hover { transform: translateY(-1px); background-color: #f8fafc; }
        .btn-danger { border: 1px solid #c0271b; background-color: #ffffff; color: #c0271b; }
        .btn-danger:hover { background-color: #fde7e4; }
        .btn-danger-solid { background-color: #c0271b; color: #ffffff; }
        .btn-sm { min-height: 2.25rem; border-radius: 0.5rem; padding-left: 0.875rem; padding-right: 0.875rem; font-size: 0.75rem; }
        .label { margin-bottom: 0.375rem; display: block; font-size: 0.875rem; font-weight: 600; color: #0b1220; }
        .hint { margin-top: 0.375rem; font-size: 0.75rem; line-height: 1.5; color: #5b6475; }
        .input { display: block; min-height: 2.75rem; width: 100%; border-radius: 0.75rem; border: 1px solid #e6e9f1; background-color: #ffffff; padding: 0.5rem 0.875rem; font-size: 0.875rem; color: #0b1220; outline: none; transition: all 0.15s; }
        .input:focus { border-color: #0d5c4a; box-shadow: 0 0 0 4px rgb(13 92 74 / 0.15); }
        textarea.input { padding-top: 0.625rem; padding-bottom: 0.625rem; line-height: 1.5; }
        .badge { display: inline-flex; align-items: center; border-radius: 9999px; padding: 0.125rem 0.625rem; font-size: 0.75rem; font-weight: 700; white-space: nowrap; }
        .badge-neutral { background-color: #f4f6fb; color: #5b6475; border: 1px solid #e6e9f1; }
        .badge-info { background-color: #e3edfc; color: #1d4f9c; }
        .badge-success { background-color: #dcf7e9; color: #0f7a4f; }
        .badge-warning { background-color: #fff1cf; color: #8a5a00; }
        .badge-danger { background-color: #fde7e4; color: #c0271b; }
        .alert { border-radius: 0.75rem; padding: 0.75rem 1rem; font-size: 0.875rem; line-height: 1.5; }
        .alert-error { background-color: #fde7e4; color: #c0271b; }
        .alert-success { background-color: #dcf7e9; color: #0f7a4f; }
        .alert-info { background-color: #e3edfc; color: #1d4f9c; }
        .alert-warning { background-color: #fff1cf; color: #8a5a00; }
        .table-wrap { overflow-x: auto; border-radius: 1rem; border: 1px solid #e6e9f1; background-color: #ffffff; box-shadow: 0 1px 2px rgb(11 18 32 / 0.04), 0 10px 30px -12px rgb(11 18 32 / 0.12); }
        .table { width: 100%; min-width: 640px; border-collapse: collapse; text-align: left; font-size: 0.875rem; }
        .table th { border-bottom: 1px solid #e6e9f1; background-color: rgba(244, 246, 251, 0.7); padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.025em; color: #5b6475; text-transform: uppercase; }
        .table td { border-bottom: 1px solid #e6e9f1; padding: 0.875rem 1rem; vertical-align: middle; }
        .table tr:last-child td { border-bottom-width: 0; }
        .link { font-weight: 600; color: #0d5c4a; text-decoration: underline; text-decoration-color: rgba(13, 92, 74, 0.3); text-underline-offset: 4px; }
        .link:hover { color: #083f33; text-decoration-color: #0d5c4a; }
    </style>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="min-h-dvh font-sans text-ink bg-canvas antialiased">
    @php
        $failedOrdersBadge = \App\Models\Order::where('status', 'failed')->count();
        $openTicketsBadge = \App\Models\Ticket::where('status', 'open')->count();
        $adminUser = auth('admin')->user();
    @endphp

    <header class="sticky top-0 z-30 border-b border-line bg-paper">
        <div class="mx-auto flex h-14 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5">
                    <svg width="28" height="28" viewBox="0 0 48 48" aria-hidden="true" class="shrink-0 shadow-sm rounded-lg">
                        <path d="M12 0H36A12 12 0 0 1 48 12V19.5A4.5 4.5 0 0 0 48 28.5V36A12 12 0 0 1 36 48H12A12 12 0 0 1 0 36V28.5A4.5 4.5 0 0 0 0 19.5V12A12 12 0 0 1 12 0Z" fill="#0d5c4a"/>
                        <path d="M16 12H23V29.5H33V36H16Z" fill="#ffffff"/>
                        <circle cx="29.5" cy="15.5" r="3.5" fill="#ff6a13"/>
                    </svg>
                    <span class="font-extrabold text-ink text-base tracking-tight">Licences</span>
                </a>
                <span class="badge badge-neutral">Backoffice</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="hidden text-xs text-muted sm:inline">{{ $adminUser->email ?? 'Administrateur' }}</span>
                <a href="{{ route('home') }}" target="_blank" class="btn btn-soft btn-sm">
                    Voir le site
                </a>
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-soft btn-sm">
                        Déconnexion
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[200px_1fr] lg:gap-8">
        <aside class="min-w-0 lg:sticky lg:top-20 lg:self-start">
            <nav aria-label="Backoffice" class="-mx-4 flex gap-1 overflow-x-auto px-4 pb-1 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0">
                <a href="{{ route('admin.dashboard') }}" class="flex min-h-10 shrink-0 items-center justify-between gap-3 rounded-xl px-3 text-sm font-semibold whitespace-nowrap transition {{ request()->routeIs('admin.dashboard') ? 'bg-brand text-white shadow-[0_10px_20px_-12px_rgb(13_92_74/0.9)]' : 'text-ink/75 hover:bg-ink/5 hover:text-ink' }}">
                    <span>Tableau de bord</span>
                </a>
                <a href="{{ route('admin.orders.index') }}" class="flex min-h-10 shrink-0 items-center justify-between gap-3 rounded-xl px-3 text-sm font-semibold whitespace-nowrap transition {{ request()->routeIs('admin.orders.*') ? 'bg-brand text-white shadow-[0_10px_20px_-12px_rgb(13_92_74/0.9)]' : 'text-ink/75 hover:bg-ink/5 hover:text-ink' }}">
                    <span>Commandes</span>
                    @if($failedOrdersBadge > 0)
                        <span class="rounded-full bg-accent px-1.5 text-[11px] font-bold text-white">{{ $failedOrdersBadge }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.products.index') }}" class="flex min-h-10 shrink-0 items-center justify-between gap-3 rounded-xl px-3 text-sm font-semibold whitespace-nowrap transition {{ request()->routeIs('admin.products.*') ? 'bg-brand text-white shadow-[0_10px_20px_-12px_rgb(13_92_74/0.9)]' : 'text-ink/75 hover:bg-ink/5 hover:text-ink' }}">
                    <span>Produits</span>
                </a>
                <a href="{{ route('admin.clients.index') }}" class="flex min-h-10 shrink-0 items-center justify-between gap-3 rounded-xl px-3 text-sm font-semibold whitespace-nowrap transition {{ request()->routeIs('admin.clients.*') ? 'bg-brand text-white shadow-[0_10px_20px_-12px_rgb(13_92_74/0.9)]' : 'text-ink/75 hover:bg-ink/5 hover:text-ink' }}">
                    <span>Clients</span>
                </a>
                <a href="{{ route('admin.tickets.index') }}" class="flex min-h-10 shrink-0 items-center justify-between gap-3 rounded-xl px-3 text-sm font-semibold whitespace-nowrap transition {{ request()->routeIs('admin.tickets.*') ? 'bg-brand text-white shadow-[0_10px_20px_-12px_rgb(13_92_74/0.9)]' : 'text-ink/75 hover:bg-ink/5 hover:text-ink' }}">
                    <span>Assistance</span>
                    @if($openTicketsBadge > 0)
                        <span class="rounded-full bg-accent px-1.5 text-[11px] font-bold text-white">{{ $openTicketsBadge }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.settings.index') }}" class="flex min-h-10 shrink-0 items-center justify-between gap-3 rounded-xl px-3 text-sm font-semibold whitespace-nowrap transition {{ request()->routeIs('admin.settings.*') ? 'bg-brand text-white shadow-[0_10px_20px_-12px_rgb(13_92_74/0.9)]' : 'text-ink/75 hover:bg-ink/5 hover:text-ink' }}">
                    <span>Paramètres</span>
                </a>
                <a href="{{ route('admin.journal.index') }}" class="flex min-h-10 shrink-0 items-center justify-between gap-3 rounded-xl px-3 text-sm font-semibold whitespace-nowrap transition {{ request()->routeIs('admin.journal.*') ? 'bg-brand text-white shadow-[0_10px_20px_-12px_rgb(13_92_74/0.9)]' : 'text-ink/75 hover:bg-ink/5 hover:text-ink' }}">
                    <span>Journal</span>
                </a>
            </nav>
        </aside>

        <main class="min-w-0">
            @if(session('success'))
                <div class="alert alert-success mb-6">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('info'))
                <div class="alert alert-info mb-6">
                    {{ session('info') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-error mb-6">
                    {{ session('error') }}
                </div>
            @endif

            @if(isset($errors) && $errors->any())
                <div class="alert alert-error mb-6">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
