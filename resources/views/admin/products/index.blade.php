@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="h1">Produits</h1>
            <p class="lead mt-2 max-w-2xl">Le catalogue vient du fournisseur. Vous décidez de ce qui est publié et à quel prix.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form action="{{ route('admin.products.sync') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-soft btn-sm">
                    Synchroniser
                </button>
            </form>
            <form action="{{ route('admin.products.publish_all') }}" method="POST" onsubmit="return confirm('Publier tous les produits vendables qui ne le sont pas encore ?');">
                @csrf
                <button type="submit" class="btn btn-brand btn-sm">
                    Publier tout le vendable
                </button>
            </form>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.products.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1 basis-60">
            <label for="q" class="label">Rechercher</label>
            <input id="q" name="q" type="search" value="{{ $q }}" maxlength="80" placeholder="Nom, catégorie ou identifiant" class="input">
        </div>
        <button type="submit" class="btn btn-soft">
            Rechercher
        </button>
        @if($q !== '')
            <a href="{{ route('admin.products.index') }}" class="btn btn-soft text-muted">
                Effacer
            </a>
        @endif
    </form>

    @if($products->count() > 0)
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">Produits</caption>
                <thead>
                    <tr>
                        <th scope="col">Produit</th>
                        <th scope="col">Fournisseur</th>
                        <th scope="col" class="text-right">Coût</th>
                        <th scope="col" class="text-right">Coût d'achat (FCFA)</th>
                        <th scope="col" class="text-right">Prix de vente</th>
                        <th scope="col" class="text-right">Bénéfice</th>
                        <th scope="col">Boutique</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $p)
                        @php
                            $profitable = $p->is_profitable;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.products.edit', $p->id) }}" class="font-medium hover:underline text-ink">
                                    {{ $p->title }}
                                </a>
                                <span class="block text-xs text-muted">{{ $p->shelf }}</span>
                            </td>
                            <td>
                                @if(!$p->supplier_active)
                                    <span class="badge badge-danger">Retiré</span>
                                @elseif(!$p->supplier_purchasable)
                                    <span class="badge badge-warning">Non vendable</span>
                                @elseif($p->in_stock)
                                    <span class="badge badge-success">{{ $p->stock === null ? 'En stock' : "{$p->stock} en stock" }}</span>
                                @else
                                    <span class="badge badge-danger">Rupture</span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap text-muted font-mono text-xs">
                                ${{ number_format($p->cost_usd_cents / 100, 2) }}
                            </td>
                            <td class="text-right whitespace-nowrap font-medium">
                                {{ number_format($p->cost_xof, 0, ',', ' ') }} F
                            </td>
                            <td class="text-right font-semibold whitespace-nowrap">
                                {{ number_format($p->price_xof, 0, ',', ' ') }} F
                                @if($p->price_xof_override)
                                    <span class="block text-xs font-normal text-muted">prix fixe</span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap {{ $profitable ? 'text-success font-medium' : 'font-semibold text-danger' }}">
                                {{ ($p->margin_xof >= 0 ? '+' : '') . number_format($p->margin_xof, 0, ',', ' ') }} F
                            </td>
                            <td>
                                @if($p->is_published)
                                    <span class="badge badge-success">Publié</span>
                                @else
                                    <span class="badge badge-neutral">Masqué</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <form action="{{ route('admin.products.toggle_active', $p->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="btn btn-soft btn-sm">
                                        {{ $p->is_published ? 'Masquer' : 'Publier' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="mt-4">
                {{ $products->links() }}
            </div>
        @endif
    @else
        <div class="card px-6 py-10 text-center">
            <p class="h3">{{ $q ? 'Aucun produit ne correspond' : 'Catalogue vide' }}</p>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">
                {{ $q ? 'Modifiez la recherche.' : 'Cliquez sur « Synchroniser » pour importer le catalogue du fournisseur.' }}
            </p>
        </div>
    @endif
</div>
@endsection
