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
        $search = trim($request->query('q', ''));
        $category = $request->query('categorie', 'toutes');

        $query = Product::where('is_published', true);

        if ($category !== 'toutes' && !empty($category)) {
            $query->where(function ($q) use ($category) {
                $q->where('shop_category', $category)
                  ->orWhere('category', $category);
            });
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('featured_rank', 'asc')
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'display_name' => $p->display_name,
                'category' => $p->category,
                'shop_category' => $p->shop_category,
                'image_url' => $p->image_url,
                'price_xof' => $p->price_xof,
                'in_stock' => (bool) $p->in_stock,
                'stock' => $p->stock,
                'preorder' => (bool) $p->preorder,
                'expire_days' => $p->expire_days,
                'warranty' => $p->warranty,
                'delivery_kind' => $p->delivery_kind,
            ]);

        $categories = Product::where('is_published', true)
            ->selectRaw('COALESCE(shop_category, category) as cat, COUNT(*) as count')
            ->groupBy('cat')
            ->orderByDesc('count')
            ->pluck('count', 'cat')
            ->toArray();

        return Inertia::render('Shop/Index', [
            'products' => $products,
            'categories' => $categories,
            'activeCategory' => $category,
            'searchQuery' => $search,
        ]);
    }

    public function show($slug)
    {
        $product = Product::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $related = Product::where('is_published', true)
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($product) {
                $q->where('category', $product->category)
                  ->orWhere('shop_category', $product->shop_category);
            })
            ->take(4)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'display_name' => $p->display_name,
                'category' => $p->category,
                'shop_category' => $p->shop_category,
                'image_url' => $p->image_url,
                'price_xof' => $p->price_xof,
                'in_stock' => (bool) $p->in_stock,
                'stock' => $p->stock,
                'preorder' => (bool) $p->preorder,
                'expire_days' => $p->expire_days,
                'warranty' => $p->warranty,
                'delivery_kind' => $p->delivery_kind,
            ]);

        return Inertia::render('Shop/Show', [
            'product' => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'display_name' => $product->display_name,
                'category' => $product->category,
                'shop_category' => $product->shop_category,
                'image_url' => $product->image_url,
                'price_xof' => $product->price_xof,
                'in_stock' => (bool) $product->in_stock,
                'stock' => $product->stock,
                'preorder' => (bool) $product->preorder,
                'expire_days' => $product->expire_days,
                'warranty' => $product->warranty,
                'delivery_kind' => $product->delivery_kind,
                'description' => $product->description,
                'instructions' => $product->instructions,
                'requires_email' => (bool) $product->requires_email,
            ],
            'related' => $related,
        ]);
    }
}
