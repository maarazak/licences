@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-2xl">
    <div>
        <h1 class="text-xl font-bold text-slate-900">Paramètres généraux</h1>
        <p class="text-xs text-slate-500 mt-0.5">Taux de change, politique tarifaire et maintenance</p>
    </div>

    <form action="{{ route('admin.settings.update') }}" method="POST" class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm space-y-6">
        @csrf

        <div class="space-y-4">
            <div>
                <label for="usd_rate" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Taux de conversion USD → FCFA</label>
                <input type="number" step="0.01" id="usd_rate" name="usd_rate" value="{{ old('usd_rate', $usdRate) }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition font-semibold text-slate-900">
                <p class="text-xs text-slate-400 mt-1">Utilisé pour convertir le coût fournisseur des abonnements en FCFA.</p>
            </div>

            <div>
                <label for="default_margin_percent" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Marge commerciale par défaut (%)</label>
                <input type="number" step="0.1" id="default_margin_percent" name="default_margin_percent" value="{{ old('default_margin_percent', $marginPercent) }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition font-semibold text-slate-900">
                <p class="text-xs text-slate-400 mt-1">Appliquée automatiquement sur les produits synchronisés sans prix fixe.</p>
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-3 cursor-pointer p-4 rounded-xl border border-slate-200 bg-slate-50">
                    <input type="checkbox" name="maintenance_mode" value="1" {{ $maintenance === '1' ? 'checked' : '' }} class="size-4 accent-teal-600 rounded">
                    <div>
                        <span class="block text-sm font-semibold text-slate-900">Mode maintenance</span>
                        <span class="block text-xs text-slate-500">Suspend temporairement les nouveaux achats sur le site public.</span>
                    </div>
                </label>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex justify-end">
            <button type="submit" class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white font-semibold text-sm rounded-xl shadow-md transition">
                Enregistrer les paramètres
            </button>
        </div>
    </form>
</div>
@endsection
