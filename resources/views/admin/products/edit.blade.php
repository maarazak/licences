@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-3xl">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.products.index') }}" class="p-2 rounded-xl border border-slate-200 hover:bg-slate-100 transition text-slate-500">
            <i data-lucide="arrow-left" class="size-4"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-900">Modifier {{ $product->name }}</h1>
            <p class="text-xs text-slate-500 mt-0.5">Paramètres d'affichage, prix et garantie</p>
        </div>
    </div>

    <form action="{{ route('admin.products.update', $product->id) }}" method="POST" class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm space-y-6">
        @csrf

        <div class="space-y-4">
            <div>
                <label for="name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Nom du produit</label>
                <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="category" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Catégorie</label>
                    <input type="text" id="category" name="category" value="{{ old('category', $product->category) }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition">
                </div>
                <div>
                    <label for="price_xof_override" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Prix fixe de vente (FCFA)</label>
                    <input type="number" id="price_xof_override" name="price_xof_override" value="{{ old('price_xof_override', $product->price_xof_override ?? $product->price_xof) }}" min="100" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition font-bold text-teal-700">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="featured_rank" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Rang d'affichage</label>
                    <input type="number" id="featured_rank" name="featured_rank" value="{{ old('featured_rank', $product->featured_rank) }}" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition">
                </div>
                <div>
                    <label for="warranty" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Garantie</label>
                    <input type="text" id="warranty" name="warranty" value="{{ old('warranty', $product->warranty) }}" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition">
                </div>
                <div>
                    <label for="expire_days" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Validité (jours)</label>
                    <input type="number" id="expire_days" name="expire_days" value="{{ old('expire_days', $product->expire_days) }}" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition">
                </div>
            </div>

            <div class="flex items-center gap-6 pt-2">
                <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-800">
                    <input type="checkbox" name="is_published" value="1" {{ $product->is_published ? 'checked' : '' }} class="size-4 accent-teal-600 rounded">
                    Actif / Publié en boutique
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-800">
                    <input type="checkbox" name="is_featured" value="1" {{ $product->is_featured ? 'checked' : '' }} class="size-4 accent-teal-600 rounded">
                    Mettre en avant (Héros)
                </label>
            </div>

            <div>
                <label for="description" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Description commerciale</label>
                <textarea id="description" name="description" rows="3" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition">{{ old('description', $product->description) }}</textarea>
            </div>

            <div>
                <label for="instructions" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Instructions de livraison</label>
                <textarea id="instructions" name="instructions" rows="3" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition">{{ old('instructions', $product->instructions) }}</textarea>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
            <a href="{{ route('admin.products.index') }}" class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium rounded-xl transition">
                Annuler
            </a>
            <button type="submit" class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold rounded-xl shadow-md transition">
                Enregistrer les modifications
            </button>
        </div>
    </form>
</div>
@endsection
