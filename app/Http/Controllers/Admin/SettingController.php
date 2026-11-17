<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $usdRate = Setting::get('usdRate', Setting::get('usd_rate', '669'));
        $marginPercent = Setting::get('marginPercent', Setting::get('default_margin_percent', '100'));
        $minMarginXof = Setting::get('minMarginXof', Setting::get('min_margin_xof', '1000'));
        $roundingStep = Setting::get('roundingStep', Setting::get('rounding_step', '250'));
        $autoPublish = (bool) Setting::get('autoPublish', Setting::get('auto_publish', '0'));
        $notice = Setting::get('notice', Setting::get('site_notice', ''));

        $checks = [
            ["Clé API fournisseur (SUPPLIER_API_KEY)", !empty(env('FLOWSELLER_API_KEY', env('SUPPLIER_API_KEY')))],
            ["Jeton API pawaPay (PAWAPAY_API_TOKEN)", !empty(env('PAWAPAY_API_TOKEN'))],
            ["Envoi d'e-mails (SMTP_HOST)", !empty(env('MAIL_HOST', env('SMTP_HOST')))],
            ["Adresse d'assistance (SUPPORT_EMAIL)", !empty(env('SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS')))],
            ["Site en HTTPS (APP_URL)", str_starts_with(env('APP_URL', ''), 'https://')],
        ];

        return view('admin.settings.index', compact(
            'usdRate',
            'marginPercent',
            'minMarginXof',
            'roundingStep',
            'autoPublish',
            'notice',
            'checks'
        ));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'usdRate' => 'required|numeric|min:1|max:100000',
            'marginPercent' => 'required|numeric|min:0|max:1000',
            'minMarginXof' => 'required|numeric|min:0|max:1000000',
            'roundingStep' => 'required|numeric|min:1|max:10000',
            'autoPublish' => 'nullable',
            'notice' => 'nullable|string|max:300',
        ]);

        $autoPublish = $request->has('autoPublish') ? '1' : '0';
        $notice = trim($validated['notice'] ?? '');

        // Enregistre les deux clés (camelCase Next.js et snake_case Laravel)
        Setting::set('usdRate', (string) $validated['usdRate']);
        Setting::set('usd_rate', (string) $validated['usdRate']);
        Setting::set('marginPercent', (string) $validated['marginPercent']);
        Setting::set('default_margin_percent', (string) $validated['marginPercent']);
        Setting::set('minMarginXof', (string) $validated['minMarginXof']);
        Setting::set('min_margin_xof', (string) $validated['minMarginXof']);
        Setting::set('roundingStep', (string) $validated['roundingStep']);
        Setting::set('rounding_step', (string) $validated['roundingStep']);
        Setting::set('autoPublish', $autoPublish);
        Setting::set('auto_publish', $autoPublish);
        Setting::set('notice', $notice);
        Setting::set('site_notice', $notice);

        Setting::clearRuntimeCache();

        AuditLog::record(
            'settings.update',
            'settings',
            [
                'details' => "taux={$validated['usdRate']} marge={$validated['marginPercent']}% minimum={$validated['minMarginXof']} arrondi={$validated['roundingStep']}"
            ]
        );

        return back()->with('success', 'Paramètres enregistrés. Les prix calculés sont à jour.');
    }
}
