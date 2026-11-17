<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $rawCount = $request->query('phares');
        $featuredCount = min(max((int) ($rawCount ?: 8), 8), 200);

        $columns = [
            'id', 'slug', 'name', 'display_name', 'shop_category', 'category',
            'image_url', 'delivery_kind', 'cost_usd_cents', 'price_xof_override',
            'margin_percent_override', 'stock', 'in_stock', 'preorder', 'expire_days',
            'warranty', 'supplier_purchasable', 'supplier_active', 'is_published',
            'is_featured', 'featured_rank', 'duration_label'
        ];

        // Produits publiés légers pour la page d'accueil (phares + choix varié)
        $allProducts = Product::where('is_published', true)
            ->where('supplier_active', true)
            ->select($columns)
            ->orderByRaw('COALESCE(featured_rank, 9999) ASC')
            ->orderBy('name', 'asc')
            ->limit(80)
            ->get();

        $mapProduct = function (Product $p) {
            $isAvailable = $p->supplier_active
                && $p->supplier_purchasable
                && $p->in_stock
                && !$p->preorder
                && ($p->stock === null || $p->stock > 0);

            $duration = $p->duration_label ?: match (true) {
                $p->expire_days >= 365 => '1 an',
                $p->expire_days === 30 => '1 mois',
                $p->expire_days > 0 => "{$p->expire_days} jours",
                default => $p->warranty ?: 'Selon offre',
            };

            $accessKind = preg_match('/account|link|code/i', (string) $p->category)
                ? $p->category
                : ($p->delivery_kind ?: 'key');

            return [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'title' => trim($p->display_name ?: $p->name),
                'display_name' => $p->display_name,
                'shelf' => trim($p->shop_category ?: $p->category ?: ''),
                'category' => $p->category,
                'shop_category' => $p->shop_category,
                'image_url' => $p->image_url,
                'price_xof' => $p->price_xof,
                'priceXof' => $p->price_xof,
                'in_stock' => (bool) $p->in_stock,
                'stock' => $p->stock,
                'preorder' => (bool) $p->preorder,
                'available' => $isAvailable,
                'delivery_kind' => $p->delivery_kind,
                'accessKind' => $accessKind,
                'duration' => $duration,
                'warranty' => $p->warranty,
                'is_featured' => (bool) $p->is_featured,
                'featured_rank' => $p->featured_rank,
            ];
        };

        $mapped = $allProducts->map($mapProduct);
        $available = $mapped->filter(fn ($p) => $p['available'])->values();

        // Produits phares configurés et disponibles
        $featuredRaw = $available
            ->filter(fn ($p) => $p['is_featured'])
            ->sortBy('featured_rank')
            ->values();

        // Catégories avec comptage exact en une seule requête SQL
        $categoriesList = Product::where('is_published', true)
            ->where('supplier_active', true)
            ->whereNotNull('shop_category')
            ->where('shop_category', '!=', '')
            ->groupBy('shop_category')
            ->selectRaw('shop_category, COUNT(*) as count')
            ->orderByDesc('count')
            ->take(8)
            ->get()
            ->map(fn ($r) => [$r->shop_category, (int) $r->count])
            ->values()
            ->all();

        return Inertia::render('Shop/Home', [
            'featured' => $featuredRaw->all(),
            'products' => $mapped->all(),
            'featuredRaw' => $featuredRaw->all(),
            'availableProducts' => $available->all(),
            'categories' => $categoriesList,
            'initialFeaturedCount' => $featuredCount,
        ]);
    }
}
