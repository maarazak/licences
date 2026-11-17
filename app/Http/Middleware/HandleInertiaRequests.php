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
        $lang = $request->cookie('lang') ?: ($user?->lang ?: 'fr');
        if (!in_array($lang, ['fr', 'en'], true)) {
            $lang = 'fr';
        }
        $currency = $request->cookie('currency') ?: ($request->cookie('devise') ?: 'XOF');
        if (!in_array($currency, ['XOF', 'USD'], true)) {
            $currency = 'XOF';
        }
        $usdRate = (int) \App\Models\Setting::get('usd_rate', 655);

        return [
            ...parent::share($request),
            'locale' => [
                'lang' => $lang,
                'currency' => $currency,
                'usdRate' => $usdRate,
            ],
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
            'searchItems' => fn () => \Illuminate\Support\Facades\Cache::remember('catalog_search_items_v2', 120, function () {
                return \App\Models\Product::where('is_published', true)
                    ->where('supplier_active', true)
                    ->orderBy('name')
                    ->limit(500)
                    ->get(['slug', 'name', 'display_name', 'shop_category'])
                    ->map(fn ($p) => [
                        'slug' => $p->slug,
                        'title' => trim($p->display_name ?: $p->name),
                        'shelf' => trim($p->shop_category ?: ''),
                    ])
                    ->values()
                    ->all();
            }),
            'notice' => fn () => \App\Models\Setting::get('site_notice', ''),
            'appName' => config('app.name', 'Licences'),
        ];
    }
}
