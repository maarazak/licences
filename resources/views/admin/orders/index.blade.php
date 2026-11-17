@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Gestion des commandes</h1>
            <p class="text-xs text-slate-500 mt-0.5">Suivi des paiements, livraisons et résolutions</p>
        </div>
        <form method="GET" action="{{ route('admin.orders.index') }}" class="flex items-center gap-2">
            @if($status) <input type="hidden" name="statut" value="{{ $status }}"> @endif
            <input type="search" name="q" value="{{ $search }}" placeholder="Réf, client ou produit..." class="px-3.5 py-2 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition w-64">
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-medium text-sm rounded-xl transition">
                Rechercher
            </button>
        </form>
    </div>

    <!-- Filtres rapides par statut -->
    <div class="flex flex-wrap gap-2 text-xs font-semibold">
        <a href="{{ route('admin.orders.index') }}" class="px-3 py-1.5 rounded-lg border transition {{ empty($status) ? 'bg-teal-600 text-white border-teal-600 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
            Toutes
        </a>
        <a href="{{ route('admin.orders.index', ['statut' => 'attente']) }}" class="px-3 py-1.5 rounded-lg border transition {{ $status === 'attente' ? 'bg-teal-600 text-white border-teal-600 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
            En attente de paiement
        </a>
        <a href="{{ route('admin.orders.index', ['statut' => 'preparation']) }}" class="px-3 py-1.5 rounded-lg border transition {{ $status === 'preparation' ? 'bg-teal-600 text-white border-teal-600 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
            En préparation / À vérifier
        </a>
        <a href="{{ route('admin.orders.index', ['statut' => 'livrees']) }}" class="px-3 py-1.5 rounded-lg border transition {{ $status === 'livrees' ? 'bg-teal-600 text-white border-teal-600 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
            Livrées
        </a>
        <a href="{{ route('admin.orders.index', ['statut' => 'remboursees']) }}" class="px-3 py-1.5 rounded-lg border transition {{ $status === 'remboursees' ? 'bg-teal-600 text-white border-teal-600 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
            Remboursées
        </a>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-3.5">Réf</th>
                        <th class="px-6 py-3.5">Client</th>
                        <th class="px-6 py-3.5">Produit</th>
                        <th class="px-6 py-3.5">Montant</th>
                        <th class="px-6 py-3.5">Statut</th>
                        <th class="px-6 py-3.5">Moyen</th>
                        <th class="px-6 py-3.5">Date</th>
                        <th class="px-6 py-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($orders as $order)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-6 py-4 font-mono text-xs font-semibold text-slate-900">
                                {{ $order->reference }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-slate-900">{{ $order->user->email ?? '—' }}</span>
                                @if($order->customer_phone)
                                    <span class="block text-xs text-slate-400">{{ $order->customer_phone }} ({{ $order->customer_country }})</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-800">
                                {{ $order->product_name }}
                                <span class="text-xs text-slate-400 block">Qté : {{ $order->quantity }}</span>
                            </td>
                            <td class="px-6 py-4 font-semibold text-slate-900">
                                {{ number_format($order->total_xof, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-6 py-4">
                                @if($order->status === 'delivered')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Livrée
                                    </span>
                                @elseif($order->status === 'paid' || $order->status === 'processing')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                        En préparation
                                    </span>
                                @elseif($order->status === 'failed')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        À vérifier
                                    </span>
                                @elseif($order->status === 'refunded')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                                        Remboursée ({{ $order->refund_method === 'balance' ? 'Solde' : 'Passerelle' }})
                                    </span>
                                @elseif($order->status === 'cancelled')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                        Annulée
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        En attente
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs">
                                {{ $order->paid_with === 'balance' ? 'Mon solde' : 'Mobile Money' }}
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                {{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : '—' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.orders.show', $order->id) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-teal-600 hover:text-teal-700 bg-teal-50 hover:bg-teal-100 px-3 py-1.5 rounded-lg transition">
                                    Gérer →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                                Aucune commande ne correspond à ces critères.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
