<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /**
     * Change la langue (fr ou en)
     */
    public function setLang(Request $request): RedirectResponse
    {
        $lang = $request->input('lang');
        if (!in_array($lang, ['fr', 'en'], true)) {
            $lang = 'fr';
        }

        $user = $request->user();
        if ($user && $user->lang !== $lang) {
            $user->update(['lang' => $lang]);
        }

        Cookie::queue('lang', $lang, 365 * 24 * 60);

        return back()->withCookie(cookie('lang', $lang, 365 * 24 * 60));
    }

    /**
     * Change la devise (XOF ou USD)
     */
    public function setCurrency(Request $request): RedirectResponse
    {
        $currency = $request->input('currency');
        if (!in_array($currency, ['XOF', 'USD'], true)) {
            $currency = 'XOF';
        }

        Cookie::queue('currency', $currency, 365 * 24 * 60);
        Cookie::queue('devise', $currency, 365 * 24 * 60);

        return back()
            ->withCookie(cookie('currency', $currency, 365 * 24 * 60))
            ->withCookie(cookie('devise', $currency, 365 * 24 * 60));
    }
}
