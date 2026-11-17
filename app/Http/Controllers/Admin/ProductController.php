<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->query('q', ''));
        $category = $request->query('categorie');

        $query = Product::orderBy('featured_rank', 'asc')->orderBy('name', 'asc');

        if ($category && $category !== 'toutes') {
            $query->where('category', $category);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $products = $query->paginate(25)->withQueryString();

        return view('admin.products.index', compact('products', 'search', 'category'));
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);

        return view('admin.products.edit', compact('product'));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'category' => 'required|string|max:50',
            'price_xof_override' => 'nullable|integer|min:100',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'featured_rank' => 'nullable|integer',
            'warranty' => 'nullable|string|max:50',
            'expire_days' => 'nullable|integer',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
        ]);

        $product->update([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'price_xof_override' => $validated['price_xof_override'] ?? null,
            'is_published' => $request->has('is_published'),
            'is_featured' => $request->has('is_featured'),
            'featured_rank' => $validated['featured_rank'] ?? 0,
            'warranty' => $validated['warranty'] ?? null,
            'expire_days' => $validated['expire_days'] ?? 0,
            'description' => $validated['description'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
        ]);

        AuditLog::record('product.update', $product->id, [
            'name' => $product->name,
            'price_override' => $product->price_xof_override,
        ]);

        return redirect()->route('admin.products.index')->with('success', "Le produit {$product->name} a été mis à jour.");
    }

    public function toggleActive($id)
    {
        $product = Product::findOrFail($id);
        $product->is_published = !$product->is_published;
        $product->save();

        $status = $product->is_published ? 'publié' : 'retiré de la vente';
        return back()->with('success', "Produit {$product->name} {$status}.");
    }

    public function syncCatalog()
    {
        try {
            $report = app(\App\Services\CatalogService::class)->syncCatalog();

            AuditLog::record('catalog.sync', 'catalog', $report);

            return back()->with('success', "Catalogue synchronisé avec succès ({$report['synced']} offres synchronisées).");
        } catch (\Throwable $e) {
            return back()->with('error', "Échec de la synchronisation du catalogue : " . $e->getMessage());
        }
    }

    public function refreshSupplierBalance()
    {
        try {
            app(\App\Services\CatalogService::class)->refreshSupplierBalance();

            AuditLog::record('supplier.balance_refresh', 'supplier');

            return back()->with('success', 'Solde revendeur Flowseller actualisé avec succès.');
        } catch (\Throwable $e) {
            return back()->with('error', "Échec de la relecture du solde fournisseur : " . $e->getMessage());
        }
    }
}
