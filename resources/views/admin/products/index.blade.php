@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Catalogue des produits</h1>
            <p class="text-xs text-slate-500 mt-0.5">Tarification, disponibilité et synchronisation fournisseur</p>
        </div>
        <div class="flex items-center gap-2">
            <form action="{{ route('admin.products.refresh_balance') }}" method="POST">
                @csrf
                <button type="submit" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-xl transition flex items-center gap-1.5">
                    <i data-lucide="wallet" class="size-3.5"></i>
                    <span>Solde Fournisseur</span>
                </button>
            </form>

            <form action="{{ route('admin.products.sync') }}" method="POST">
                @csrf
                <button type="submit" class="px-3.5 py-2 bg-teal-600 hover:bg-teal-700 text-white font-semibold text-xs rounded-xl shadow-sm transition flex items-center gap-1.5">
                    <i data-lucide="refresh-cw" class="size-3.5"></i>
                    <span>Synchroniser catalogue</span>
                </button>
            </form>

            <form method="GET" action="{{ route('admin.products.index') }}" class="flex items-center gap-2">
                <input type="search" name="q" value="{{ $search }}" placeholder="Rechercher..." class="px-3 py-2 rounded-xl border border-slate-300 text-xs focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition w-48">
                <button type="submit" class="px-3 py-2 bg-slate-800 hover:bg-slate-900 text-white font-medium text-xs rounded-xl transition">
                    Filtrer
                </button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-3.5">Nom du produit</th>
                        <th class="px-6 py-3.5">Catégorie</th>
                        <th class="px-6 py-3.5">Prix fixe (FCFA)</th>
                        <th class="px-6 py-3.5">Fournisseur ($)</th>
                        <th class="px-6 py-3.5">En vente</th>
                        <th class="px-6 py-3.5">Mis en avant</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($products as $product)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-900 block">{{ $product->name }}</span>
                                <span class="text-xs text-slate-400 font-mono">{{ $product->slug }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700">
                                    {{ ucfirst($product->category) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-bold text-teal-700">
                                {{ number_format($product->price_xof, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-6 py-4 text-xs font-mono text-slate-500">
                                ${{ number_format($product->cost_usd_cents / 100, 2) }}
                            </td>
                            <td class="px-6 py-4">
                                <form action="{{ route('admin.products.toggle_active', $product->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border transition {{ $product->is_published ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500 border-slate-200 hover:bg-slate-200' }}">
                                        {{ $product->is_published ? 'Publié' : 'Masqué' }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                {{ $product->is_featured ? '⭐ Oui' : '—' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.products.edit', $product->id) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-teal-600 hover:text-teal-700 bg-teal-50 hover:bg-teal-100 px-3 py-1.5 rounded-lg transition">
                                    Modifier →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                Aucun produit trouvé.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($products->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
