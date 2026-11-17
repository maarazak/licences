<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $featured = Product::where('is_published', true)
            ->where('is_featured', true)
            ->orderBy('featured_rank', 'asc')
            ->orderBy('name', 'asc')
            ->take(8)
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

        // If less than 4 featured, take popular published products
        if ($featured->count() < 4) {
            $extra = Product::where('is_published', true)
                ->whereNotIn('id', $featured->pluck('id'))
                ->orderBy('name', 'asc')
                ->take(8 - $featured->count())
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
            $featured = $featured->concat($extra);
        }

        $categories = Product::where('is_published', true)
            ->selectRaw('COALESCE(shop_category, category) as cat, COUNT(*) as count')
            ->groupBy('cat')
            ->orderByDesc('count')
            ->pluck('count', 'cat')
            ->toArray();

        return Inertia::render('Shop/Home', [
            'featured' => $featured,
            'categories' => $categories,
        ]);
    }
}
