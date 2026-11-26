@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <nav aria-label="Fil d'Ariane" class="text-xs text-muted">
        <a href="{{ route('admin.products.index') }}" class="hover:text-ink">
            Produits
        </a>
        / <span class="text-ink">{{ $product->name }}</span>
    </nav>

    <h1 class="h1">{{ $product->title }}</h1>

    @if(!$product->isManual() && !$product->is_profitable)
        <p class="alert alert-error">Le prix de vente ne couvre pas le coût : la vente de ce produit est bloquée. Corrigez le prix fixe, la marge ou le taux USD.</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_340px] lg:items-start">
        <form action="{{ route('admin.products.update', $product->id) }}" method="POST" @if($product->isManual()) enctype="multipart/form-data" @endif class="card space-y-5 p-5">
            @csrf

            <div class="flex flex-wrap gap-6">
                <label class="flex cursor-pointer items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="isPublished" value="1" {{ old('isPublished', $product->is_published) ? 'checked' : '' }} class="size-4 accent-brand">
                    Publié dans la boutique
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="isFeatured" value="1" {{ old('isFeatured', $product->is_featured) ? 'checked' : '' }} class="size-4 accent-brand">
                    Mis en avant
                </label>
            </div>

            @if($product->isManual())
                <div>
                    <label for="name" class="label">Nom du produit</label>
                    <input id="name" name="name" value="{{ old('name', $product->name) }}" maxlength="255" required class="input">
                </div>
                <div>
                    <label for="image" class="label">Image du produit</label>
                    @if($product->image_url)
                        <div class="mb-2 flex items-center gap-3">
                            <img src="{{ $product->image_url }}" alt="" class="size-16 rounded-xl border border-line bg-white object-contain p-1">
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-muted">
                                <input type="checkbox" name="removeImage" value="1" class="size-4 accent-brand">
                                Retirer l'image
                            </label>
                        </div>
                    @endif
                    <input id="image" name="image" type="file" accept="image/png,image/jpeg,image/webp" class="input py-2">
                    <p class="hint">{{ $product->image_url ? 'Choisir un fichier remplace l\'image actuelle.' : 'PNG, JPG ou WebP, 2 Mo maximum, 64 px minimum. Sans image, un logo est choisi d\'après le nom.' }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="accessKind" class="label">Type d'accès livré</label>
                        <select id="accessKind" name="accessKind" class="input">
                            @foreach($accessKinds as $value => $label)
                                <option value="{{ $value }}" {{ old('accessKind', $product->delivery_kind) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('admin.products._select', ['id' => 'warranty', 'name' => 'warranty', 'label' => 'Garantie', 'options' => $warranties, 'selected' => old('warranty', $product->warranty), 'empty' => 'Choisir une garantie'])
                </div>
            @else
            <div>
                <label for="displayName" class="label">Nom affiché</label>
                <input id="displayName" name="displayName" value="{{ old('displayName', $product->display_name) }}" maxlength="255" placeholder="{{ $product->name }}" class="input">
                <p class="hint">Vide = nom du fournisseur.</p>
            </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                @include('admin.products._select', ['id' => 'shopCategory', 'name' => 'shopCategory', 'label' => 'Catégorie boutique', 'options' => $categories, 'selected' => old('shopCategory', $product->shop_category), 'empty' => 'Choisir une catégorie', 'hint' => 'Sert au filtre de la boutique.'])
                @include('admin.products._select', ['id' => 'durationLabel', 'name' => 'durationLabel', 'label' => 'Durée affichée', 'options' => array_combine($durations, $durations), 'selected' => old('durationLabel', $product->duration_label), 'empty' => $product->isManual() ? 'Choisir une durée' : 'Automatique (durée du fournisseur)'])
                <div>
                    <label for="priceXofOverride" class="label">Prix fixe (FCFA)</label>
                    <input id="priceXofOverride" name="priceXofOverride" inputmode="numeric" value="{{ old('priceXofOverride', $product->price_xof_override) }}" {{ $product->isManual() ? 'required' : '' }} class="input">
                    <p class="hint">{{ $product->isManual() ? 'Prix de vente de ce produit.' : 'Vide = prix calculé (coût × taux × marge).' }}</p>
                </div>
                @unless($product->isManual())
                <div>
                    <label for="marginPercentOverride" class="label">Marge propre (%)</label>
                    <input id="marginPercentOverride" name="marginPercentOverride" inputmode="numeric" value="{{ old('marginPercentOverride', $product->margin_percent_override) }}" class="input">
                    <p class="hint">Vide = marge générale ({{ $settings['marginPercent'] }} %).</p>
                </div>
                @endunless
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="featuredRank" class="label">Rang « produit phare »</label>
                    <input id="featuredRank" name="featuredRank" inputmode="numeric" value="{{ old('featuredRank', $product->featured_rank) }}" class="input">
                    <p class="hint">1 = affiché en premier sur l'accueil. Vide = pas un produit phare (cochez aussi « Mis en avant »).</p>
                </div>
            </div>

            <div>
                <label for="description" class="label">Description (français)</label>
                <textarea id="description" name="description" rows="4" maxlength="5000" class="input">{{ old('description', $product->description) }}</textarea>
            </div>

            <div>
                <label for="descriptionEn" class="label">Description (anglais)</label>
                <textarea id="descriptionEn" name="descriptionEn" rows="4" maxlength="5000" class="input">{{ old('descriptionEn', $product->description_en) }}</textarea>
                <p class="hint">Vide = la version française est affichée.</p>
            </div>

            <div>
                <label for="instructionsEn" class="label">Mode d'emploi (anglais)</label>
                <textarea id="instructionsEn" name="instructionsEn" rows="4" maxlength="5000" class="input">{{ old('instructionsEn', $product->instructions_en) }}</textarea>
            </div>

            <div>
                <label for="instructions" class="label">Mode d'emploi (français)</label>
                <textarea id="instructions" name="instructions" rows="5" maxlength="5000" class="input">{{ old('instructions', $product->instructions) }}</textarea>
                <p class="hint">Affiché sur la fiche et avec les accès livrés. Vide = le texte du fournisseur est montré à l'acheteur avec ses accès.</p>
            </div>

            @if($product->supplier_description)
                <details class="rounded-lg border border-line p-3">
                    <summary class="cursor-pointer text-sm font-medium">Texte du fournisseur (non affiché sur la fiche)</summary>
                    <p class="mt-3 text-sm text-muted whitespace-pre-line">{{ $product->supplier_description }}</p>
                </details>
            @endif

            <div class="pt-2">
                <button type="submit" class="btn btn-brand">Enregistrer</button>
            </div>
        </form>

        <aside class="space-y-6">
        @if($product->isManual())
        <div class="card space-y-4 px-5 py-4">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="h3">Stock</h2>
                <p class="text-sm text-muted"><strong class="text-ink">{{ $stock['available'] }}</strong> disponible(s) · {{ $stock['sold'] }} vendu(s)</p>
            </div>
            <form action="{{ route('admin.products.stock_add', $product->id) }}" method="POST" class="space-y-2">
                @csrf
                <label for="stockItems" class="label">Ajouter du stock (un accès par ligne)</label>
                <textarea id="stockItems" name="stockItems" rows="4" required class="input font-mono text-xs" placeholder="email:motdepasse"></textarea>
                <button type="submit" class="btn btn-brand btn-sm">Ajouter au stock</button>
            </form>
            @if($stock['items']->count())
                <details class="rounded-lg border border-line p-3">
                    <summary class="cursor-pointer text-sm font-medium">Accès disponibles (aperçu masqué)</summary>
                    <ul class="mt-3 divide-y divide-line text-xs">
                        @foreach($stock['items'] as $item)
                            <li class="space-y-2 py-2">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-mono text-muted">{{ $item['preview'] }}</span>
                                    <form action="{{ route('admin.products.stock_remove', [$product->id, $item['id']]) }}" method="POST" data-confirm="Retirer cet accès du stock ?">
                                        @csrf
                                        <button type="submit" class="btn btn-soft btn-sm">Retirer</button>
                                    </form>
                                </div>
                                <form action="{{ route('admin.products.stock_update', [$product->id, $item['id']]) }}" method="POST" class="flex gap-2">
                                    @csrf
                                    <input name="content" required maxlength="2000" placeholder="Nouveau contenu de cet accès" class="input min-h-9 font-mono text-xs" aria-label="Nouveau contenu de l'accès">
                                    <button type="submit" class="btn btn-soft btn-sm shrink-0">Modifier</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
        <div class="card space-y-2 px-5 py-4 text-sm">
            <p class="text-muted">Produit manuel : la synchronisation du fournisseur ne le modifie jamais.</p>
            <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST" data-confirm="Supprimer définitivement ce produit et son stock ?">
                @csrf
                <button type="submit" class="btn btn-soft btn-sm text-danger">Supprimer le produit</button>
            </form>
        </div>
        @endif
        <div class="card px-5 py-4 space-y-3">
            <dl class="divide-y divide-line text-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Prix de vente</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ number_format($product->price_xof, 0, ',', ' ') }} FCFA</dd>
                </div>
                @unless($product->isManual())
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Coût fournisseur</dt>
                    <dd class="min-w-0 text-right font-medium text-ink font-mono text-xs">
                        ${{ number_format($product->cost_usd_cents / 100, 2) }} ≈ {{ number_format($product->cost_xof, 0, ',', ' ') }} FCFA
                    </dd>
                </div>
                @endunless
                @unless($product->isManual())
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Bénéfice unitaire</dt>
                    <dd class="min-w-0 text-right font-semibold {{ $product->is_profitable ? 'text-success' : 'text-danger' }}">
                        {{ ($product->margin_xof >= 0 ? '+' : '') . number_format($product->margin_xof, 0, ',', ' ') }} FCFA
                    </dd>
                </div>
                @endunless
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Stock</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $product->stock === null ? ($product->in_stock ? 'Disponible' : 'Rupture') : $product->stock }}</dd>
                </div>
                @unless($product->isManual())
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Vendable</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $product->supplier_purchasable && $product->supplier_active ? 'Oui' : 'Non' }}</dd>
                </div>
                @endunless
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Type d'accès</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $product->delivery_kind ?: '—' }}</dd>
                </div>
                @unless($product->isManual())
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">E-mail requis</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $product->requires_email ? 'Oui' : 'Non' }}</dd>
                </div>
                @endunless
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Garantie</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $product->warranty ?: 'Aucune' }}</dd>
                </div>
                @unless($product->isManual())
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Identifiant fournisseur</dt>
                    <dd class="min-w-0 text-right font-mono text-xs text-ink">{{ $product->supplier_id }}</dd>
                </div>
                @endunless
                @unless($product->isManual())
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Synchronisé le</dt>
                    <dd class="min-w-0 text-right font-medium text-ink text-xs">{{ $product->synced_at ? $product->synced_at->format('d/m/Y H:i') : '—' }}</dd>
                </div>
                @endunless
            </dl>
        </div>
        </aside>
    </div>
</div>
@endsection
