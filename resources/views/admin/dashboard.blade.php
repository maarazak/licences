@extends('layouts.admin')

@section('content')
<div class="space-y-8">
    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Chiffre d'affaires</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($totalRevenueXof, 0, ',', ' ') }} FCFA</p>
                <p class="text-xs text-teal-600 font-medium mt-1">Encaissements confirmés</p>
            </div>
            <div class="size-12 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center shrink-0">
                <i data-lucide="wallet" class="size-6"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Commandes</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ $totalOrders }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ $deliveredCount }} livrées avec succès</p>
            </div>
            <div class="size-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <i data-lucide="shopping-bag" class="size-6"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">À traiter / Échecs</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ $failedCount }}</p>
                <p class="text-xs {{ $failedCount > 0 ? 'text-amber-600 font-semibold' : 'text-slate-400' }} mt-1">
                    {{ $failedCount > 0 ? 'Action requise de l\'équipe' : 'Tout est à jour' }}
                </p>
            </div>
            <div class="size-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <i data-lucide="alert-triangle" class="size-6"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Catalogue Actif</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ $activeProductsCount }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ $clientsCount }} clients enregistrés</p>
            </div>
            <div class="size-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                <i data-lucide="layers" class="size-6"></i>
            </div>
        </div>
    </div>

    <!-- Recent Orders Section -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-slate-900">Dernières commandes</h2>
                <p class="text-xs text-slate-500 mt-0.5">Flux d'achats en temps réel</p>
            </div>
            <a href="{{ route('admin.orders.index') }}" class="text-xs font-semibold text-teal-600 hover:text-teal-700 flex items-center gap-1">
                Voir toutes les commandes →
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-3.5">Référence</th>
                        <th class="px-6 py-3.5">Client</th>
                        <th class="px-6 py-3.5">Produit</th>
                        <th class="px-6 py-3.5">Montant</th>
                        <th class="px-6 py-3.5">Statut</th>
                        <th class="px-6 py-3.5">Date</th>
                        <th class="px-6 py-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($recentOrders as $order)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-6 py-4 font-mono text-xs font-semibold text-slate-900">
                                {{ $order->reference }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-slate-900">{{ $order->user->email ?? 'Client invité' }}</span>
                                @if($order->customer_phone)
                                    <span class="block text-xs text-slate-400">{{ $order->customer_phone }} ({{ $order->customer_country }})</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-800">
                                {{ $order->product_name }}
                                <span class="text-xs text-slate-400 block">Qté: {{ $order->quantity }}</span>
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
                                        Remboursée
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
                            <td class="px-6 py-4 text-xs text-slate-500">
                                {{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : '—' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.orders.show', $order->id) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-teal-600 hover:text-teal-700 bg-teal-50 hover:bg-teal-100 px-3 py-1.5 rounded-lg transition">
                                    Détails →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-slate-400">
                                Aucune commande trouvée.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
