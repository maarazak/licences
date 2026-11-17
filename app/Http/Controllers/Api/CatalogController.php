<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('categorie');
        $search = trim($request->query('q', ''));
        $featured = $request->boolean('featured');

        $query = Product::where('is_published', true);

        if ($category && $category !== 'toutes') {
            $query->where(function ($q) use ($category) {
                $q->where('shop_category', $category)
                  ->orWhere('category', $category);
            });
        }

        if ($featured) {
            $query->where('is_featured', true);
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
            ->map(function ($p) {
                $p->price_xof = $p->price_xof;
                return $p;
            });

        return response()->json([
            'success' => true,
            'products' => $products,
        ]);
    }

    public function show($slug)
    {
        $product = Product::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $product->price_xof = $product->price_xof;

        $related = Product::where('is_published', true)
            ->where('category', $product->category)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get()
            ->map(function ($p) {
                $p->price_xof = $p->price_xof;
                return $p;
            });

        return response()->json([
            'success' => true,
            'product' => $product,
            'related' => $related,
        ]);
    }
}
