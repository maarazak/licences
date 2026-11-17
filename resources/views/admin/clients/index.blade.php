@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Clients & Portefeuilles</h1>
            <p class="text-xs text-slate-500 mt-0.5">Soldes, historique d'achats et blocages</p>
        </div>
        <form method="GET" action="{{ route('admin.clients.index') }}" class="flex items-center gap-2">
            <input type="search" name="q" value="{{ $search }}" placeholder="E-mail ou téléphone..." class="px-3.5 py-2 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition w-64">
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-medium text-sm rounded-xl transition">
                Rechercher
            </button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-3.5">E-mail</th>
                        <th class="px-6 py-3.5">Nom complet</th>
                        <th class="px-6 py-3.5">Téléphone</th>
                        <th class="px-6 py-3.5">Pays</th>
                        <th class="px-6 py-3.5">Solde (FCFA)</th>
                        <th class="px-6 py-3.5">Statut</th>
                        <th class="px-6 py-3.5">Inscrit le</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($clients as $client)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-6 py-4 font-semibold text-slate-900">
                                {{ $client->email }}
                            </td>
                            <td class="px-6 py-4 text-slate-700">
                                {{ trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) ?: '—' }}
                            </td>
                            <td class="px-6 py-4 text-xs font-mono text-slate-600">
                                {{ $client->phone ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-xs uppercase font-semibold text-slate-500">
                                {{ $client->country ?? 'BF' }}
                            </td>
                            <td class="px-6 py-4 font-bold text-teal-700">
                                {{ number_format($client->balance_xof, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-6 py-4">
                                @if($client->is_blocked)
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                                        Bloqué
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Actif
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                {{ $client->created_at ? $client->created_at->format('d/m/Y') : '—' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.clients.show', $client->id) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-teal-600 hover:text-teal-700 bg-teal-50 hover:bg-teal-100 px-3 py-1.5 rounded-lg transition">
                                    Fiche client →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                                Aucun client trouvé.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($clients->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $clients->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
