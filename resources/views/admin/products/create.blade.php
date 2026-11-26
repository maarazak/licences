@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <nav aria-label="Fil d'Ariane" class="text-xs text-muted">
        <a href="{{ route('admin.products.index') }}" class="hover:text-ink">Produits</a>
        / <span class="text-ink">Nouveau produit</span>
    </nav>

    <div>
        <h1 class="h1">Nouveau produit</h1>
        <p class="lead mt-2 max-w-2xl">Un produit que vous gérez vous-même : il apparaît sur le site, se vend et se livre depuis votre stock. La synchronisation du fournisseur ne le modifie jamais.</p>
    </div>

    @if($errors->any())
        <p class="alert alert-error">{{ $errors->first() }}</p>
    @endif

    <form action="{{ route('admin.products.store') }}" method="POST" class="card max-w-3xl space-y-5 p-5">
        @csrf

        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium">
            <input type="checkbox" name="isPublished" value="1" {{ old('isPublished', '1') ? 'checked' : '' }} class="size-4 accent-brand">
            Publié dans la boutique dès la création
        </label>

        <div>
            <label for="name" class="label">Nom du produit</label>
            <input id="name" name="name" value="{{ old('name') }}" maxlength="255" required class="input" placeholder="Netflix Premium 1 mois">
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="priceXof" class="label">Prix de vente (FCFA)</label>
                <input id="priceXof" name="priceXof" inputmode="numeric" value="{{ old('priceXof') }}" required class="input" placeholder="5000">
            </div>
            <div>
                <label for="accessKind" class="label">Type d'accès livré</label>
                <select id="accessKind" name="accessKind" class="input">
                    @foreach($accessKinds as $value => $label)
                        <option value="{{ $value }}" {{ old('accessKind', 'account') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="shopCategory" class="label">Catégorie boutique</label>
                <input id="shopCategory" name="shopCategory" value="{{ old('shopCategory') }}" maxlength="100" placeholder="Divertissement, IA et assistants…" class="input">
            </div>
            <div>
                <label for="durationLabel" class="label">Durée affichée</label>
                <input id="durationLabel" name="durationLabel" value="{{ old('durationLabel') }}" maxlength="60" placeholder="1 mois" class="input">
            </div>
            <div>
                <label for="warranty" class="label">Garantie</label>
                <input id="warranty" name="warranty" value="{{ old('warranty') }}" maxlength="100" placeholder="7 jours" class="input">
            </div>
        </div>

        <div>
            <label for="stockItems" class="label">Stock : un accès par ligne</label>
            <textarea id="stockItems" name="stockItems" rows="6" class="input font-mono text-xs" placeholder="email1@exemple.com:motdepasse1&#10;email2@exemple.com:motdepasse2">{{ old('stockItems') }}</textarea>
            <p class="hint">Chaque ligne est un accès livré à un client (compte, lien ou code). Le stock affiché est le nombre de lignes. Les accès sont chiffrés en base. Vous pourrez en ajouter plus tard.</p>
        </div>

        <div>
            <label for="description" class="label">Description</label>
            <textarea id="description" name="description" rows="4" maxlength="5000" class="input">{{ old('description') }}</textarea>
        </div>

        <div>
            <label for="instructions" class="label">Mode d'emploi</label>
            <textarea id="instructions" name="instructions" rows="4" maxlength="5000" class="input">{{ old('instructions') }}</textarea>
            <p class="hint">Affiché sur la fiche et avec les accès livrés.</p>
        </div>

        <div class="pt-2">
            <button type="submit" class="btn btn-brand">Créer le produit</button>
        </div>
    </form>
</div>
@endsection
