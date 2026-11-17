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

        // Tous les produits publiés
        $allProducts = Product::where('is_published', true)
            ->orderBy('shop_category', 'asc')
            ->orderBy('name', 'asc')
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

        // Catégories avec comptage
        $categoryCounts = [];
        foreach ($allProducts as $p) {
            $shelf = trim($p->shop_category ?: $p->category ?: '');
            if ($shelf) {
                $categoryCounts[$shelf] = ($categoryCounts[$shelf] ?? 0) + 1;
            }
        }
        arsort($categoryCounts);

        $categoriesList = [];
        foreach ($categoryCounts as $cat => $cnt) {
            $categoriesList[] = [$cat, $cnt];
        }

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
