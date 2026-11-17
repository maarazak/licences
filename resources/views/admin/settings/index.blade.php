@extends('admin.layouts.app')

@section('title', 'Paramètres — Backoffice')

@section('content')
<div class="max-w-3xl space-y-8">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Paramètres de la boutique</h1>
        <p class="text-xs text-slate-500 mt-1">
            Gestion du taux de change, de la marge commerciale et du bandeau d'information.
        </p>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center gap-2">
            <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('admin.settings.update') }}" method="POST" class="bg-white rounded-2xl border border-slate-200/80 p-6 sm:p-8 shadow-sm space-y-6">
        @csrf
        <div>
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Tarification & Marge</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mt-4">
                <div>
                    <label for="usdRate" class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Taux du dollar : 1 $ en FCFA
                    </label>
                    <input
                        type="number"
                        id="usdRate"
                        name="usdRate"
                        value="{{ old('usdRate', $usdRate) }}"
                        required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition"
                    >
                    <p class="mt-1 text-[11px] text-slate-400">
                        Taux de conversion appliqué pour calculer le coût d'achat et le prix de vente en FCFA.
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
                        value="{{ old('marginPercent', $marginPercent) }}"
                        required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition"
                    >
                    <p class="mt-1 text-[11px] text-slate-400">
                        Pourcentage appliqué au coût d'achat fournisseur (ex. 100 = x2).
                    </p>
                </div>

                <div>
                    <label for="minMarginXof" class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Marge minimale par vente (FCFA)
                    </label>
                    <input
                        type="number"
                        id="minMarginXof"
                        name="minMarginXof"
                        value="{{ old('minMarginXof', $minMarginXof) }}"
                        required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition"
                    >
                    <p class="mt-1 text-[11px] text-slate-400">
                        Garantit un gain minimum même sur les produits d'entrée de gamme.
                    </p>
                </div>

                <div>
                    <label for="roundingStep" class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Pas d'arrondi (FCFA)
                    </label>
                    <input
                        type="number"
                        id="roundingStep"
                        name="roundingStep"
                        value="{{ old('roundingStep', $roundingStep) }}"
                        required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition"
                    >
                    <p class="mt-1 text-[11px] text-slate-400">
                        Ex. 250 arrondit aux multiples supérieurs : 1 750, 2 000, 2 250...
                    </p>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-100 pt-6 space-y-4">
            <h2 class="text-base font-bold text-slate-900">Boutique & Vitrine</h2>

            <label class="flex cursor-pointer items-center gap-3 select-none">
                <input
                    type="checkbox"
                    name="autoPublish"
                    value="1"
                    {{ $autoPublish ? 'checked' : '' }}
                    class="size-4 rounded text-teal-600 focus:ring-teal-500 border-slate-300"
                >
                <span class="text-xs font-semibold text-slate-700">
                    Publier automatiquement les nouveaux produits synchronisés du fournisseur
                </span>
            </label>

            <div>
                <label for="notice" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Bandeau d'information public
                </label>
                <input
                    type="text"
                    id="notice"
                    name="notice"
                    value="{{ old('notice', $notice) }}"
                    maxlength="300"
                    placeholder="Ex. ⚡ Livraison automatique 24h/24 par Mobile Money"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition"
                >
                <p class="mt-1 text-[11px] text-slate-400">
                    Affiché en haut de toutes les pages du site. Laisser vide pour masquer le bandeau.
                </p>
            </div>
        </div>

        <div class="border-t border-slate-100 pt-5 flex justify-end">
            <button
                type="submit"
                class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-semibold text-xs rounded-xl shadow-sm transition"
            >
                Enregistrer les paramètres
            </button>
        </div>
    </form>

    <!-- Diagnostics Serveur -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-6 sm:p-8 shadow-sm space-y-4">
        <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Configuration du serveur (.env)</h2>
        <p class="text-xs text-slate-500">
            Ces réglages proviennent de l'environnement applicatif. Les clés secrètes ne sont jamais affichées publiquement.
        </p>

        <ul class="space-y-2.5 text-xs">
            @foreach($checks as [$label, $ok])
                <li class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="font-medium text-slate-800">{{ $label }}</span>
                    @if($ok)
                        <span class="inline-flex items-center gap-1 font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Configuré
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 font-semibold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-lg border border-rose-200">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Manquant
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
