<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Setting;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->query('q', ''));

        $query = Product::orderBy('supplier_active', 'desc')
            ->orderBy('is_published', 'desc')
            ->orderBy('name', 'asc');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('display_name', 'like', "%{$q}%")
                    ->orWhere('shop_category', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%")
                    ->orWhere('supplier_id', 'like', "%{$q}%");
            });
        }

        $products = $query->paginate(50)->withQueryString();

        $settings = [
            'usdRate' => (int) Setting::get('usdRate', Setting::get('usd_rate', 669)),
            'marginPercent' => (int) Setting::get('marginPercent', Setting::get('default_margin_percent', 100)),
        ];

        return view('admin.products.index', compact('products', 'q', 'settings'));
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        $settings = [
            'usdRate' => (int) Setting::get('usdRate', Setting::get('usd_rate', 669)),
            'marginPercent' => (int) Setting::get('marginPercent', Setting::get('default_margin_percent', 100)),
        ];

        return view('admin.products.edit', compact('product', 'settings'));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'displayName' => 'nullable|string|max:255',
            'shopCategory' => 'nullable|string|max:100',
            'durationLabel' => 'nullable|string|max:60',
            'priceXofOverride' => 'nullable|integer|min:0',
            'marginPercentOverride' => 'nullable|numeric|min:0|max:1000',
            'featuredRank' => 'nullable|integer',
            'description' => 'nullable|string|max:5000',
            'descriptionEn' => 'nullable|string|max:5000',
            'instructions' => 'nullable|string|max:5000',
            'instructionsEn' => 'nullable|string|max:5000',
        ]);

        $product->update([
            'is_published' => $request->has('isPublished') || $request->has('is_published'),
            'is_featured' => $request->has('isFeatured') || $request->has('is_featured'),
            'display_name' => $validated['displayName'] ?? $request->input('display_name'),
            'shop_category' => $validated['shopCategory'] ?? $request->input('shop_category'),
            'duration_label' => $validated['durationLabel'] ?? $request->input('duration_label'),
            'price_xof_override' => $validated['priceXofOverride'] ?? $request->input('price_xof_override'),
            'margin_percent_override' => $validated['marginPercentOverride'] ?? $request->input('margin_percent_override'),
            'featured_rank' => $validated['featuredRank'] ?? $request->input('featured_rank'),
            'description' => $validated['description'] ?? null,
            'description_en' => $validated['descriptionEn'] ?? $request->input('description_en'),
            'instructions' => $validated['instructions'] ?? null,
            'instructions_en' => $validated['instructionsEn'] ?? $request->input('instructions_en'),
        ]);

        AuditLog::record('product.update', $product->id, [
            'name' => $product->name,
            'price_override' => $product->price_xof_override,
        ]);

        return redirect()->route('admin.products.edit', $product->id)->with('success', "Le produit {$product->name} a été enregistré.");
    }

    public function toggleActive($id)
    {
        $product = Product::findOrFail($id);
        $product->is_published = !$product->is_published;
        $product->save();

        AuditLog::record('produits.toggle-publication', $product->id, [
            'is_published' => $product->is_published,
        ]);

        $status = $product->is_published ? 'publié' : 'masqué';
        return back()->with('success', "Produit « {$product->title} » {$status}.");
    }

    public function publishAllSellable()
    {
        $count = Product::where('supplier_active', true)
            ->where('supplier_purchasable', true)
            ->where('preorder', false)
            ->where('is_published', false)
            ->update([
                'is_published' => true,
                'updated_at' => now(),
            ]);

        AuditLog::record('produits.publier-tout', 'catalog', ['count' => $count]);

        return back()->with('success', "{$count} produit(s) vendable(s) publié(s).");
    }

    public function syncCatalog()
    {
        try {
            $report = app(\App\Services\CatalogService::class)->syncCatalog(false);
            if (empty($report['cached']) || empty($report['cooldown'])) {
                app(\App\Services\CatalogService::class)->refreshSupplierBalance();
            }

            AuditLog::record('catalogue.synchroniser', 'catalog', $report);

            if (!empty($report['cached'])) {
                if (!empty($report['cooldown'])) {
                    $msg = "Le catalogue local est à jour ({$report['total']} produits). Le fournisseur applique une temporisation (disponible dans {$report['cooldown']}s).";
                } else {
                    $msg = "Catalogue déjà à jour ({$report['total']} offres disponibles).";
                }
                return back()->with('info', $msg);
            }

            $msg = "Catalogue synchronisé avec succès ({$report['total']} offres traitées, {$report['created']} créées, {$report['updated']} mises à jour).";
            return back()->with('success', $msg);
        } catch (\App\Services\FlowsellerException $e) {
            $retry = $e->retryAfterSeconds > 0 ? " Veuillez patienter {$e->retryAfterSeconds}s avant de relancer." : "";
            if (Product::count() > 0) {
                return back()->with('info', "Le catalogue local actuel (" . Product::count() . " produits) reste actif. Le fournisseur demande de patienter{$retry}");
            }
            return back()->with('error', "Le fournisseur limite temporairement les requêtes.{$retry}");
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
