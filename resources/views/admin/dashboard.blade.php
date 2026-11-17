@extends('layouts.admin')

@section('content')
<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="h1">Tableau de bord</h1>
        </div>
    </div>

    @if(($counts['failed'] ?? 0) > 0)
        <div class="alert alert-warning flex flex-wrap items-center justify-between gap-3">
            <span>
                <strong>{{ $counts['failed'] }}</strong> commande(s) payée(s) non livrée(s) attendent une décision.
            </span>
            <a href="{{ route('admin.orders.index', ['filtre' => 'a-traiter']) }}" class="btn btn-soft btn-sm">
                Traiter
            </a>
        </div>
    @endif

    @if(!empty($settings['supplierBalanceError']))
        <p class="alert alert-error">Fournisseur injoignable : {{ $settings['supplierBalanceError'] }}. Le solde affiché date de la dernière lecture réussie.</p>
    @endif

    <section>
        <h2 class="h3 mb-3">Ventes livrées</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="card p-4">
                <p class="text-xs font-semibold text-muted">24 dernières heures</p>
                <p class="mt-1 font-display text-2xl font-bold tracking-tight text-ink">{{ number_format($today['total'], 0, ',', ' ') }} FCFA</p>
                <p class="mt-1 text-xs text-muted">{{ $today['orders'] }} commande(s) · marge {{ number_format($today['margin'], 0, ',', ' ') }} FCFA</p>
            </div>
            <div class="card p-4">
                <p class="text-xs font-semibold text-muted">7 derniers jours</p>
                <p class="mt-1 font-display text-2xl font-bold tracking-tight text-ink">{{ number_format($week['total'], 0, ',', ' ') }} FCFA</p>
                <p class="mt-1 text-xs text-muted">{{ $week['orders'] }} commande(s) · marge {{ number_format($week['margin'], 0, ',', ' ') }} FCFA</p>
            </div>
            <div class="card p-4">
                <p class="text-xs font-semibold text-muted">30 derniers jours</p>
                <p class="mt-1 font-display text-2xl font-bold tracking-tight text-ink">{{ number_format($month['total'], 0, ',', ' ') }} FCFA</p>
                <p class="mt-1 text-xs text-muted">{{ $month['orders'] }} commande(s) · marge {{ number_format($month['margin'], 0, ',', ' ') }} FCFA</p>
            </div>
        </div>
    </section>

    <section>
        <h2 class="h3 mb-3">Activité</h2>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="card p-4">
                <p class="text-xs font-semibold text-muted">Solde fournisseur</p>
                <p class="mt-1 font-display text-2xl font-bold tracking-tight text-ink">
                    @if($settings['supplierBalanceCents'] > 0 || $settings['supplierBalanceAt'])
                        ${{ number_format($settings['supplierBalanceCents'] / 100, 2) }}
                    @else
                        —
                    @endif
                </p>
                <p class="mt-1 text-xs text-muted">
                    @if($settings['supplierBalanceAt'])
                        Lu le {{ \Carbon\Carbon::parse($settings['supplierBalanceAt'])->format('d/m/Y H:i') }} · rechargé dans le bot Telegram
                    @else
                        Rechargé dans le bot Telegram
                    @endif
                </p>
            </div>
            <a href="{{ route('admin.orders.index', ['filtre' => 'en-cours']) }}" class="card block p-4 transition duration-300 hover:-translate-y-0.5 hover:shadow-pop">
                <p class="text-xs font-semibold text-muted">Commandes en cours</p>
                <p class="mt-1 font-display text-2xl font-bold tracking-tight text-ink">{{ $inProgress }}</p>
            </a>
            <a href="{{ route('admin.tickets.index') }}" class="card block p-4 transition duration-300 hover:-translate-y-0.5 hover:shadow-pop">
                <p class="text-xs font-semibold text-muted">Demandes ouvertes</p>
                <p class="mt-1 font-display text-2xl font-bold tracking-tight text-ink">{{ $openTickets }}</p>
            </a>
            <a href="{{ route('admin.clients.index') }}" class="card block p-4 transition duration-300 hover:-translate-y-0.5 hover:shadow-pop">
                <p class="text-xs font-semibold text-muted">Clients</p>
                <p class="mt-1 font-display text-2xl font-bold tracking-tight text-ink">{{ $customers }}</p>
            </a>
        </div>
    </section>

    <section class="card space-y-3 p-5">
        <h2 class="h3">Catalogue</h2>
        <p class="text-sm text-muted">
            {{ $catalogPublished }} produit(s) publié(s) sur {{ $catalogTotal }} · {{ $catalogSellable }} vendable(s) chez le fournisseur · dernière synchronisation : 
            {{ $settings['lastCatalogSyncAt'] ? \Carbon\Carbon::parse($settings['lastCatalogSyncAt'])->format('d/m/Y H:i') : 'jamais' }}
        </p>
        <p class="text-sm text-muted">Synchronisation automatique chaque minute : prix, stock et état sont recopiés du fournisseur dans notre base ; les visiteurs ne voient que notre base.</p>
        
        @if(!empty($settings['catalogSyncError']))
            <p class="alert alert-error">Dernière synchronisation en échec : {{ $settings['catalogSyncError'] }}. Le catalogue enregistré reste en vente.</p>
        @endif

        <form action="{{ route('admin.products.sync') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-brand btn-sm">
                Synchroniser maintenant
            </button>
        </form>
    </section>
</div>
@endsection
