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

        // Tous les produits publiés pour la page d'accueil (sans limite artificielle)
        $allProducts = Product::where('is_published', true)
            ->where('supplier_active', true)
            ->select($columns)
            ->orderByRaw('COALESCE(featured_rank, 9999) ASC')
            ->orderBy('name', 'asc')
            ->get();

        $mapProduct = function (Product $p) {
            $isAvailable = $p->supplier_active
                && $p->supplier_purchasable
                && $p->in_stock
                && !$p->preorder
                && ($p->stock === null || $p->stock > 0);

            $duration = $p->duration;

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

        // Mise en avant automatique par marque (d'après le nom des produits, voir config/shop.php), sans réglage manuel.
        // Les rangs « phare » définis à la main dans le backoffice restent prioritaires.
        $highlights = \App\Support\BrandHighlights::apply($mapped);
        $mapped = $highlights['products'];

        // Tri : produits EN STOCK d'abord (ceux en rupture restent affichés, à la suite), puis rang phare, puis nom.
        $sorted = $mapped->sort(function ($a, $b) {
            if ($a['available'] !== $b['available']) {
                return (int) $b['available'] <=> (int) $a['available'];
            }
            $rankA = $a['featured_rank'] ?? 9999;
            $rankB = $b['featured_rank'] ?? 9999;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }
            return strcasecmp($a['title'], $b['title']);
        })->values();

        // Carrousel : les produits aux rangs manuels 1..10 s'il y en a assez, sinon le meilleur produit disponible de chaque marque.
        $manualCarousel = $mapped
            ->filter(fn ($p) => ($p['featured_rank'] ?? 9999) <= 10)
            ->sortBy('featured_rank')
            ->values();
        $carouselProducts = $manualCarousel->count() >= 4 ? $manualCarousel : $highlights['carousel'];

        // Produits phares généraux
        $featuredRaw = $sorted
            ->filter(fn ($p) => !empty($p['is_featured']))
            ->values(); // garde l'ordre de $sorted : en stock d'abord

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
            'featured' => $carouselProducts->all(),
            'carouselProducts' => $carouselProducts->all(),
            'products' => $sorted->all(),
            'featuredRaw' => $featuredRaw->all(),
            'availableProducts' => $sorted->all(),
            'categories' => $categoriesList,
            'initialFeaturedCount' => $featuredCount,
        ]);
    }
}
