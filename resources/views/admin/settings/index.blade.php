@extends('layouts.admin')

@section('content')
<div class="max-w-2xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="h1">Paramètres</h1>
        </div>
    </div>

    <form action="{{ route('admin.settings.update') }}" method="POST" class="card space-y-5 p-5">
        @csrf

        <h2 class="h3">Prix</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="usdRate" class="label">
                    Taux du dollar : 1 $ en FCFA
                </label>
                <input id="usdRate" name="usdRate" inputmode="numeric" required value="{{ old('usdRate', $usdRate) }}" class="input">
                <p class="hint">Un seul taux pour le coût d'achat, les prix de vente, le blocage des ventes à perte et l'affichage en dollars sur le site (« Taux du jour »).</p>
            </div>
            <div>
                <label for="marginPercent" class="label">
                    Marge générale (%)
                </label>
                <input id="marginPercent" name="marginPercent" inputmode="numeric" required value="{{ old('marginPercent', $marginPercent) }}" class="input">
            </div>
            <div>
                <label for="minMarginXof" class="label">
                    Marge minimale par vente (FCFA)
                </label>
                <input id="minMarginXof" name="minMarginXof" inputmode="numeric" required value="{{ old('minMarginXof', $minMarginXof) }}" class="input">
            </div>
            <div>
                <label for="roundingStep" class="label">
                    Arrondi (FCFA)
                </label>
                <input id="roundingStep" name="roundingStep" inputmode="numeric" required value="{{ old('roundingStep', $roundingStep) }}" class="input">
            </div>
        </div>
        <p class="hint">Prix de vente = le plus élevé de « coût en $ × taux × (1 + marge) » et « coût + marge minimale », arrondi au multiple supérieur (250 FCFA donne des prix ronds : 1 750, 2 000, 2 250…). La marge minimale garantit un gain par vente même sur les produits très bon marché. Prévoyez dans la marge les frais du prestataire de paiement et le coût de recharge du solde fournisseur. Un produit peut avoir son propre prix fixe (prioritaire) ou sa propre marge en %.</p>

        <h2 class="h3 border-t border-line pt-5">Boutique</h2>
        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium">
            <input type="checkbox" name="autoPublish" value="1" {{ old('autoPublish', $autoPublish) ? 'checked' : '' }} class="size-4 accent-brand">
            Publier automatiquement les nouveaux produits du fournisseur
        </label>
        <div>
            <label for="notice" class="label">
                Bandeau d'information
            </label>
            <input id="notice" name="notice" maxlength="300" value="{{ old('notice', $notice) }}" class="input">
            <p class="hint">Affiché en haut de toutes les pages. Vide = masqué.</p>
        </div>
        <button type="submit" class="btn btn-brand">Enregistrer</button>
    </form>

    <section class="card p-5">
        <h2 class="h3">Configuration du serveur</h2>
        <p class="hint mb-3">Ces réglages se trouvent dans le fichier .env. Les valeurs ne sont jamais affichées.</p>
        <ul class="space-y-1.5 text-sm">
            @foreach($checks as $check)
                <li class="flex items-center justify-between gap-4">
                    <span>{{ $check[0] }}</span>
                    <span class="badge {{ $check[1] ? 'badge-success' : 'badge-danger' }}">{{ $check[1] ? 'Configuré' : 'Manquant' }}</span>
                </li>
            @endforeach
        </ul>
    </section>
</div>
@endsection
