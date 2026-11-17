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

    @if(!$product->is_profitable)
        <p class="alert alert-error">Le prix de vente ne couvre pas le coût : la vente de ce produit est bloquée. Corrigez le prix fixe, la marge ou le taux USD.</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_340px] lg:items-start">
        <form action="{{ route('admin.products.update', $product->id) }}" method="POST" class="card space-y-5 p-5">
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

            <div>
                <label for="displayName" class="label">Nom affiché</label>
                <input id="displayName" name="displayName" value="{{ old('displayName', $product->display_name) }}" maxlength="255" placeholder="{{ $product->name }}" class="input">
                <p class="hint">Vide = nom du fournisseur.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="shopCategory" class="label">Catégorie boutique</label>
                    <input id="shopCategory" name="shopCategory" value="{{ old('shopCategory', $product->shop_category ?? $product->category) }}" maxlength="100" placeholder="IA et assistants, Création, Bureautique…" class="input">
                    <p class="hint">Sert au filtre de la boutique.</p>
                </div>
                <div>
                    <label for="durationLabel" class="label">Durée affichée</label>
                    <input id="durationLabel" name="durationLabel" value="{{ old('durationLabel', $product->duration_label) }}" maxlength="60" placeholder="{{ $product->expire_days ? $product->expire_days . ' jours' : '' }}" class="input">
                    <p class="hint">Vide = durée annoncée par le fournisseur.</p>
                </div>
                <div>
                    <label for="priceXofOverride" class="label">Prix fixe (FCFA)</label>
                    <input id="priceXofOverride" name="priceXofOverride" inputmode="numeric" value="{{ old('priceXofOverride', $product->price_xof_override) }}" class="input">
                    <p class="hint">Vide = prix calculé (coût × taux × marge).</p>
                </div>
                <div>
                    <label for="marginPercentOverride" class="label">Marge propre (%)</label>
                    <input id="marginPercentOverride" name="marginPercentOverride" inputmode="numeric" value="{{ old('marginPercentOverride', $product->margin_percent_override) }}" class="input">
                    <p class="hint">Vide = marge générale ({{ $settings['marginPercent'] }} %).</p>
                </div>
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

        <aside class="card px-5 py-4 space-y-3">
            <dl class="divide-y divide-line text-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Prix de vente</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ number_format($product->price_xof, 0, ',', ' ') }} FCFA</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Coût fournisseur</dt>
                    <dd class="min-w-0 text-right font-medium text-ink font-mono text-xs">
                        ${{ number_format($product->cost_usd_cents / 100, 2) }} ≈ {{ number_format($product->cost_xof, 0, ',', ' ') }} FCFA
                    </dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Bénéfice unitaire</dt>
                    <dd class="min-w-0 text-right font-semibold {{ $product->is_profitable ? 'text-success' : 'text-danger' }}">
                        {{ ($product->margin_xof >= 0 ? '+' : '') . number_format($product->margin_xof, 0, ',', ' ') }} FCFA
                    </dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Stock</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $product->stock === null ? ($product->in_stock ? 'Disponible' : 'Rupture') : $product->stock }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Vendable</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $product->supplier_purchasable && $product->supplier_active ? 'Oui' : 'Non' }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Type d'accès</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $product->delivery_kind ?: '—' }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">E-mail requis</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $product->requires_email ? 'Oui' : 'Non' }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Garantie</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $product->warranty ?: 'Aucune' }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Identifiant fournisseur</dt>
                    <dd class="min-w-0 text-right font-mono text-xs text-ink">{{ $product->supplier_id }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Synchronisé le</dt>
                    <dd class="min-w-0 text-right font-medium text-ink text-xs">{{ $product->synced_at ? $product->synced_at->format('d/m/Y H:i') : '—' }}</dd>
                </div>
            </dl>
        </aside>
    </div>
</div>
@endsection
