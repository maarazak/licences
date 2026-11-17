@extends('layouts.admin')

@section('title', 'Paramètres — Backoffice')

@section('content')
<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Paramètres</h1>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center gap-2">
            <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="p-4 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('admin.settings.update') }}" method="POST" class="bg-white rounded-2xl border border-slate-200/80 p-6 sm:p-7 shadow-sm space-y-5">
        @csrf

        <h2 class="text-base font-bold text-slate-900">Prix</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="usdRate" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Taux du dollar : 1 $ en FCFA
                </label>
                <input
                    type="number"
                    id="usdRate"
                    name="usdRate"
                    inputmode="numeric"
                    required
                    value="{{ old('usdRate', $usdRate) }}"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition"
                >
                <p class="mt-1 text-[11px] text-slate-400">
                    Un seul taux pour le coût d'achat, les prix de vente, le blocage des ventes à perte et l'affichage en dollars sur le site (« Taux du jour »).
                </p>
            </div>

            <div>
                <label for="marginPercent" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Marge générale (%)
                </label>
                <input
                    type="number"
                    id="marginPercent"
                    name="marginPercent"
                    inputmode="numeric"
                    required
                    value="{{ old('marginPercent', $marginPercent) }}"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition"
                >
            </div>

            <div>
                <label for="minMarginXof" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Marge minimale par vente (FCFA)
                </label>
                <input
                    type="number"
                    id="minMarginXof"
                    name="minMarginXof"
                    inputmode="numeric"
                    required
                    value="{{ old('minMarginXof', $minMarginXof) }}"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition"
                >
            </div>

            <div>
                <label for="roundingStep" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Arrondi (FCFA)
                </label>
                <input
                    type="number"
                    id="roundingStep"
                    name="roundingStep"
                    inputmode="numeric"
                    required
                    value="{{ old('roundingStep', $roundingStep) }}"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition"
                >
            </div>
        </div>

        <p class="text-[11px] leading-relaxed text-slate-400">
            Prix de vente = le plus élevé de « coût en $ × taux × (1 + marge) » et « coût + marge minimale », arrondi au multiple supérieur (250 FCFA donne des prix ronds : 1 750, 2 000, 2 250…). La marge minimale garantit un gain par vente même sur les produits très bon marché. Prévoyez dans la marge les frais du prestataire de paiement et le coût de recharge du solde fournisseur. Un produit peut avoir son propre prix fixe (prioritaire) ou sa propre marge en %.
        </p>

        <h2 class="text-base font-bold text-slate-900 border-t border-slate-100 pt-5">Boutique</h2>
        <label class="flex cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-700">
            <input
                type="checkbox"
                name="autoPublish"
                value="1"
                {{ $autoPublish ? 'checked' : '' }}
                class="size-4 rounded text-teal-600 focus:ring-teal-500 border-slate-300 accent-teal-600"
            >
            <span>Publier automatiquement les nouveaux produits du fournisseur</span>
        </label>

        <div>
            <label for="notice" class="block text-xs font-semibold text-slate-700 mb-1.5">
                Bandeau d'information
            </label>
            <input
                type="text"
                id="notice"
                name="notice"
                maxlength="300"
                value="{{ old('notice', $notice) }}"
                placeholder="Ex. ⚡ Livraison automatique 24h/24 par Mobile Money"
                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition"
            >
            <p class="mt-1 text-[11px] text-slate-400">
                Affiché en haut de toutes les pages. Vide = masqué.
            </p>
        </div>

        <div class="pt-2">
            <button
                type="submit"
                class="px-6 py-2.5 bg-teal-700 hover:bg-teal-800 text-white font-semibold text-sm rounded-xl shadow-sm transition cursor-pointer"
            >
                Enregistrer
            </button>
        </div>
    </form>

    <!-- Configuration du serveur (.env) -->
    <section class="bg-white rounded-2xl border border-slate-200/80 p-6 sm:p-7 shadow-sm space-y-3">
        <h2 class="text-base font-bold text-slate-900">Configuration du serveur</h2>
        <p class="text-[11px] text-slate-400 mb-3">
            Ces réglages se trouvent dans le fichier .env. Les valeurs ne sont jamais affichées.
        </p>

        <ul class="space-y-1.5 text-sm">
            @foreach($checks as [$label, $ok])
                <li class="flex items-center justify-between gap-4 py-1.5 border-b border-slate-50 last:border-0">
                    <span class="text-xs text-slate-700">{{ $label }}</span>
                    @if($ok)
                        <span class="inline-flex items-center gap-1 font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-lg border border-emerald-200 text-xs">
                            Configuré
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 font-semibold text-rose-700 bg-rose-50 px-2.5 py-0.5 rounded-lg border border-rose-200 text-xs">
                            Manquant
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
</div>
@endsection
