@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-5xl">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.clients.index') }}" class="p-2 rounded-xl border border-slate-200 hover:bg-slate-100 transition text-slate-500">
                <i data-lucide="arrow-left" class="size-4"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ $client->email }}</h1>
                <p class="text-xs text-slate-500 mt-0.5">Inscrit le {{ $client->created_at ? $client->created_at->format('d/m/Y') : '—' }}</p>
            </div>
        </div>

        <div>
            <form id="form-toggle-block" action="{{ route('admin.clients.toggle_block', $client->id) }}" method="POST">
                @csrf
                <button type="button" onclick="confirmAction({
                    title: '{{ $client->is_blocked ? 'Débloquer ce client ?' : 'Bloquer ce client ?' }}',
                    text: '{{ $client->is_blocked ? 'Le client pourra à nouveau se connecter et passer commande.' : 'Le client ne pourra plus se connecter ni commander.' }}',
                    confirmText: '{{ $client->is_blocked ? 'Débloquer' : 'Bloquer' }}',
                    formId: 'form-toggle-block'
                })" class="px-3.5 py-2 text-xs font-semibold rounded-xl border transition {{ $client->is_blocked ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100' }}">
                    {{ $client->is_blocked ? 'Débloquer le client' : 'Bloquer le client' }}
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Carte Solde & Ajustement -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">Portefeuille client</h2>
            <div class="text-center py-2">
                <span class="text-xs text-slate-400 block uppercase tracking-wider">Solde disponible</span>
                <span class="text-3xl font-extrabold text-teal-700 block mt-1">{{ number_format($client->balance_xof, 0, ',', ' ') }} FCFA</span>
            </div>

            <form action="{{ route('admin.clients.adjust_balance', $client->id) }}" method="POST" class="pt-3 border-t border-slate-100 space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Montant à créditer (+) ou débiter (-)</label>
                    <input type="number" name="amount_xof" placeholder="Ex: 5000 ou -2000" required class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Motif de l'ajustement</label>
                    <input type="text" name="reason" placeholder="Ex: Geste commercial, régularisation..." required class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition">
                </div>
                <button type="submit" class="w-full py-2 bg-teal-600 hover:bg-teal-700 text-white font-semibold text-xs rounded-xl shadow-sm transition">
                    Appliquer l'ajustement
                </button>
            </form>
        </div>

        <!-- Coordonnées & Historique -->
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">Commandes récentes du client</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 border-b border-slate-100">
                            <tr>
                                <th class="pb-2 font-medium">Référence</th>
                                <th class="pb-2 font-medium">Produit</th>
                                <th class="pb-2 font-medium">Montant</th>
                                <th class="pb-2 font-medium">Statut</th>
                                <th class="pb-2 font-medium text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            @forelse($client->orders as $order)
                                <tr>
                                    <td class="py-2.5 font-mono font-semibold">
                                        <a href="{{ route('admin.orders.show', $order->id) }}" class="text-teal-600 hover:underline">
                                            {{ $order->reference }}
                                        </a>
                                    </td>
                                    <td class="py-2.5">{{ $order->product_name }}</td>
                                    <td class="py-2.5 font-semibold">{{ number_format($order->total_xof, 0, ',', ' ') }} FCFA</td>
                                    <td class="py-2.5">{{ strtoupper($order->status) }}</td>
                                    <td class="py-2.5 text-right text-slate-400">{{ $order->created_at->format('d/m/Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-slate-400">Aucune commande.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Historique des mouvements de solde -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">Mouvements de solde récents</h2>
                <div class="space-y-2.5">
                    @forelse($client->walletEntries as $entry)
                        <div class="flex items-center justify-between text-xs p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <div>
                                <p class="font-semibold text-slate-800">{{ $entry->description }}</p>
                                <p class="text-slate-400 text-[11px]">{{ $entry->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                            <div class="text-right">
                                <span class="font-bold {{ $entry->amount_xof >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ $entry->amount_xof >= 0 ? '+' : '' }}{{ number_format($entry->amount_xof, 0, ',', ' ') }} FCFA
                                </span>
                                <span class="block text-[11px] text-slate-400">Solde : {{ number_format($entry->balance_after_xof, 0, ',', ' ') }} FCFA</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">Aucun mouvement de solde.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
