<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStockItem;
use App\Models\Setting;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /** Types d'accès proposés pour un produit manuel (la valeur sert aussi à l'affichage « Compte fourni », « Lien »…). */
    public const ACCESS_KINDS = ['account' => 'Compte fourni', 'link' => 'Lien', 'code' => 'Code / clé', 'key' => 'Clé de licence'];

    /** Une ligne = un accès à livrer. Lignes vides et doublons ignorés ; 500 accès au plus par envoi. */
    private static function parseStockLines(?string $text): array
    {
        $lines = array_map(fn ($l) => mb_substr(trim($l), 0, 2000), preg_split('/\R/u', (string) $text) ?: []);

        return array_slice(array_values(array_unique(array_filter($lines, fn ($l) => $l !== ''))), 0, 500);
    }

    public function create()
    {
        return view('admin.products.create', ['accessKinds' => self::ACCESS_KINDS]);
    }

    /** Crée un produit géré à la main : publié ou non à votre choix, jamais modifié par la synchronisation du fournisseur. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'accessKind' => 'required|in:' . implode(',', array_keys(self::ACCESS_KINDS)),
            'priceXof' => 'required|integer|min:1|max:10000000',
            'shopCategory' => 'nullable|string|max:100',
            'durationLabel' => 'nullable|string|max:60',
            'warranty' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:5000',
            'instructions' => 'nullable|string|max:5000',
            'stockItems' => 'nullable|string|max:300000',
        ]);

        $baseSlug = Str::slug($validated['name']) ?: 'produit';
        $slug = $baseSlug;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . Str::lower(Str::random(5));
        }

        $lines = self::parseStockLines($validated['stockItems'] ?? '');

        $product = DB::transaction(function () use ($validated, $slug, $lines, $request) {
            $product = Product::create([
                'id' => Str::uuid()->toString(),
                'supplier_id' => 'manual-' . Str::uuid()->toString(),
                'source' => 'manual',
                'slug' => $slug,
                'name' => $validated['name'],
                'display_name' => $validated['name'],
                'category' => $validated['accessKind'],
                'delivery_kind' => $validated['accessKind'],
                'shop_category' => $validated['shopCategory'] ?? null,
                'duration_label' => $validated['durationLabel'] ?? null,
                'warranty' => $validated['warranty'] ?? '',
                'description' => $validated['description'] ?? null,
                'instructions' => $validated['instructions'] ?? null,
                'price_xof_override' => (int) $validated['priceXof'],
                'cost_usd_cents' => 0,
                'list_usd_cents' => 0,
                'stock' => 0,
                'in_stock' => false,
                'preorder' => false,
                'requires_email' => false,
                'supplier_purchasable' => true,
                'supplier_active' => true,
                'is_published' => $request->has('isPublished'),
            ]);
            foreach ($lines as $line) {
                ProductStockItem::add($product->id, $line);
            }
            $product->refreshManualStock();

            return $product;
        });

        AuditLog::record('product.create', $product->id, ['name' => $product->name, 'stock' => count($lines)]);

        return redirect()->route('admin.products.edit', $product->id)->with('success', "Le produit « {$product->name} » a été créé (" . count($lines) . ' accès en stock).');
    }

    /** Ajoute du stock (un accès par ligne) à un produit manuel. */
    public function addStock(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        abort_unless($product->isManual(), 404);

        $validated = $request->validate(['stockItems' => 'required|string|max:300000']);
        $lines = self::parseStockLines($validated['stockItems']);
        if (!$lines) {
            return back()->with('error', 'Aucun accès à ajouter : saisissez un accès par ligne.');
        }

        DB::transaction(function () use ($product, $lines) {
            foreach ($lines as $line) {
                ProductStockItem::add($product->id, $line);
            }
            $product->refreshManualStock();
        });

        AuditLog::record('product.stock_add', $product->id, ['count' => count($lines)]);

        return back()->with('success', count($lines) . ' accès ajouté(s) au stock.');
    }

    /** Retire un accès encore disponible du stock (un accès déjà vendu ne se supprime pas). */
    public function removeStockItem($id, $itemId)
    {
        $product = Product::findOrFail($id);
        abort_unless($product->isManual(), 404);

        $deleted = ProductStockItem::where('id', $itemId)->where('product_id', $product->id)->where('status', 'available')->delete();
        $product->refreshManualStock();

        return back()->with($deleted ? 'success' : 'error', $deleted ? 'Accès retiré du stock.' : 'Accès introuvable ou déjà vendu.');
    }

    /** Supprime un produit manuel qui n'a jamais été commandé. */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        abort_unless($product->isManual(), 404);

        if ($product->orders()->exists()) {
            return back()->with('error', 'Ce produit a déjà été commandé : masquez-le plutôt que de le supprimer.');
        }

        DB::transaction(function () use ($product) {
            $product->stockItems()->delete();
            $product->delete();
        });
        AuditLog::record('product.delete', $id, ['name' => $product->name]);

        return redirect()->route('admin.products.index')->with('success', "Le produit « {$product->name} » a été supprimé.");
    }

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

        $stock = null;
        if ($product->isManual()) {
            $stock = [
                'available' => $product->stockItems()->where('status', 'available')->count(),
                'sold' => $product->stockItems()->where('status', 'sold')->count(),
                // Aperçu masqué : le contenu complet n'est jamais ré-affiché dans l'administration.
                'items' => $product->stockItems()->where('status', 'available')->orderBy('created_at')->limit(200)->get()->map(function ($item) {
                    $plain = (string) $item->plain();

                    return ['id' => $item->id, 'preview' => mb_substr($plain, 0, 4) . str_repeat('•', min(12, max(0, mb_strlen($plain) - 4))), 'created_at' => $item->created_at];
                }),
            ];
        }
        $accessKinds = self::ACCESS_KINDS;

        return view('admin.products.edit', compact('product', 'settings', 'stock', 'accessKinds'));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        if ($product->isManual()) {
            return $this->updateManual($request, $product);
        }

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

    private function updateManual(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'accessKind' => 'required|in:' . implode(',', array_keys(self::ACCESS_KINDS)),
            'priceXofOverride' => 'required|integer|min:1|max:10000000',
            'shopCategory' => 'nullable|string|max:100',
            'durationLabel' => 'nullable|string|max:60',
            'warranty' => 'nullable|string|max:100',
            'featuredRank' => 'nullable|integer',
            'description' => 'nullable|string|max:5000',
            'descriptionEn' => 'nullable|string|max:5000',
            'instructions' => 'nullable|string|max:5000',
            'instructionsEn' => 'nullable|string|max:5000',
        ]);

        $product->update([
            'name' => $validated['name'],
            'display_name' => $validated['name'],
            'category' => $validated['accessKind'],
            'delivery_kind' => $validated['accessKind'],
            'is_published' => $request->has('isPublished'),
            'is_featured' => $request->has('isFeatured'),
            'shop_category' => $validated['shopCategory'] ?? null,
            'duration_label' => $validated['durationLabel'] ?? null,
            'warranty' => $validated['warranty'] ?? '',
            'price_xof_override' => (int) $validated['priceXofOverride'],
            'featured_rank' => $validated['featuredRank'] ?? null,
            'description' => $validated['description'] ?? null,
            'description_en' => $validated['descriptionEn'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'instructions_en' => $validated['instructionsEn'] ?? null,
        ]);
        $product->refreshManualStock();

        AuditLog::record('product.update', $product->id, ['name' => $product->name, 'price_override' => $product->price_xof_override]);

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
            ->where('source', '!=', 'manual') // les produits manuels se publient volontairement
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
