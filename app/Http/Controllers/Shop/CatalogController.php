<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $q = mb_substr(trim($request->query('q', '')), 0, 80);
        $category = trim($request->query('categorie', ''));
        $availability = $request->query('dispo') === 'disponible' ? 'available' : 'all';
        $sort = $request->query('tri', 'featured');
        if (!in_array($sort, ['featured', 'price_asc', 'price_desc', 'name'], true)) {
            $sort = 'featured';
        }

        $pageSize = 12;
        $shown = min(max((int) ($request->query('n') ?: $pageSize), $pageSize), 500);

        // Récupération de tous les rayons distincts publiés
        $categories = Product::where('is_published', true)
            ->where('supplier_active', true)
            ->whereNotNull('shop_category')
            ->where('shop_category', '!=', '')
            ->distinct()
            ->orderBy('shop_category')
            ->pluck('shop_category')
            ->values()
            ->all();

        // Si la catégorie demandée n'existe pas dans la liste, on la réinitialise
        if ($category && !in_array($category, $categories, true)) {
            $category = '';
        }

        $columns = [
            'id', 'slug', 'name', 'display_name', 'shop_category', 'category',
            'image_url', 'delivery_kind', 'cost_usd_cents', 'price_xof_override',
            'margin_percent_override', 'stock', 'in_stock', 'preorder', 'expire_days',
            'warranty', 'supplier_purchasable', 'supplier_active', 'is_published',
            'is_featured', 'featured_rank', 'duration_label'
        ];

        // Query produits
        $query = Product::where('is_published', true)
            ->where('supplier_active', true)
            ->select($columns);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('display_name', 'like', "%{$q}%")
                    ->orWhere('shop_category', 'like', "%{$q}%");
            });
        }

        if ($category !== '') {
            $query->where('shop_category', $category);
        }

        // Mapping produits avec calculs métier identiques à catalog.ts
        $products = $query->limit(500)->get()->map(function (Product $p) {
            $isAvailable = $p->supplier_purchasable
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
                'isFeatured' => (bool) $p->is_featured,
                'featured_rank' => $p->featured_rank,
            ];
        });

        // Filtre de disponibilité
        if ($availability === 'available') {
            $products = $products->filter(fn ($p) => $p['available'])->values();
        }

        // Tri identique à listCatalog()
        if ($sort === 'price_asc') {
            $products = $products->sortBy('price_xof')->values();
        } elseif ($sort === 'price_desc') {
            $products = $products->sortByDesc('price_xof')->values();
        } elseif ($sort === 'name') {
            $products = $products->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)->values();
        } else {
            // 'featured' : disponibles d'abord, puis par rang phare asc, puis nom
            $products = $products->sort(function ($a, $b) {
                if ($a['available'] !== $b['available']) {
                    return $b['available'] <=> $a['available'];
                }
                $rankA = $a['featured_rank'] ?? 9999;
                $rankB = $b['featured_rank'] ?? 9999;
                if ($rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }
                return strcasecmp($a['title'], $b['title']);
            })->values();
        }

        return Inertia::render('Shop/Index', [
            'products' => $products->all(),
            'categories' => $categories,
            'activeCategory' => $category,
            'searchQuery' => $q,
            'availability' => $availability,
            'sort' => $sort,
            'shown' => $shown,
        ]);
    }

    public function show($slug)
    {
        $product = Product::where('slug', $slug)
            ->where('is_published', true)
            ->where('supplier_active', true)
            ->firstOrFail();

        $isAvailable = $product->supplier_purchasable
            && $product->in_stock
            && !$product->preorder
            && ($product->stock === null || $product->stock > 0);

        $duration = $product->duration;

        $accessKind = preg_match('/account|link|code/i', (string) $product->category)
            ? $product->category
            : ($product->delivery_kind ?: 'key');

        $productData = [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'title' => trim($product->display_name ?: $product->name),
            'display_name' => $product->display_name,
            'shelf' => trim($product->shop_category ?: $product->category ?: ''),
            'category' => $product->category,
            'shop_category' => $product->shop_category,
            'image_url' => $product->image_url,
            'price_xof' => $product->price_xof,
            'priceXof' => $product->price_xof,
            'in_stock' => (bool) $product->in_stock,
            'stock' => $product->stock,
            'preorder' => (bool) $product->preorder,
            'available' => $isAvailable,
            'delivery_kind' => $product->delivery_kind,
            'accessKind' => $accessKind,
            'duration' => $duration,
            'warranty' => $product->warranty,
            'description' => $product->description,
            'description_en' => $product->description_en,
            'instructions' => $product->instructions,
            'instructions_en' => $product->instructions_en,
            'requires_email' => (bool) $product->requires_email,
            'is_featured' => (bool) $product->is_featured,
        ];

        // Offres associées pour la même marque / rayon
        $firstWord = explode(' ', trim($product->name))[0] ?? '';
        $related = Product::where('is_published', true)
            ->where('supplier_active', true)
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($firstWord, $product) {
                if ($firstWord) {
                    $q->where('name', 'like', "{$firstWord}%");
                }
                if ($product->shop_category) {
                    $q->orWhere('shop_category', $product->shop_category);
                }
            })
            ->take(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'title' => trim($p->display_name ?: $p->name),
                'display_name' => $p->display_name,
                'shelf' => trim($p->shop_category ?: $p->category ?: ''),
                'price_xof' => $p->price_xof,
                'priceXof' => $p->price_xof,
                'duration' => $p->duration_label ?: ($p->expire_days ? "{$p->expire_days} jours" : 'Selon offre'),
                'available' => (bool) $p->in_stock && !$p->preorder,
            ]);

        return Inertia::render('Shop/Show', [
            'product' => $productData,
            'related' => $related->all(),
        ]);
    }
}
