<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'balance_xof' => (int) ($user->balance_xof ?? 0),
                    'country' => $user->country ?? 'BF',
                    'lang' => $user->lang ?? 'fr',
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'searchItems' => fn () => \Illuminate\Support\Facades\Cache::remember('catalog_search_items', 60, function () {
                return \App\Models\Product::where('is_published', true)
                    ->where('supplier_active', true)
                    ->where('supplier_purchasable', true)
                    ->where('in_stock', true)
                    ->where('preorder', false)
                    ->where(function ($q) {
                        $q->whereNull('stock')->orWhere('stock', '>', 0);
                    })
                    ->orderBy('name')
                    ->get(['slug', 'name', 'display_name', 'shop_category', 'category'])
                    ->map(fn ($p) => [
                        'slug' => $p->slug,
                        'title' => trim($p->display_name ?: $p->name),
                        'shelf' => trim($p->shop_category ?: $p->category ?: ''),
                    ]);
            }),
            'notice' => fn () => \App\Models\Setting::get('site_notice', ''),
            'appName' => config('app.name', 'Licences'),
        ];
    }
}
