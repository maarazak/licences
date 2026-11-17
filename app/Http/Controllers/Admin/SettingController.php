<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    public function index()
    {
        $usdRate = Setting::get('usd_rate', '669');
        $marginPercent = Setting::get('default_margin_percent', '100');
        $minMarginXof = Setting::get('min_margin_xof', '1000');
        $roundingStep = Setting::get('rounding_step', '250');
        $autoPublish = (bool) Setting::get('auto_publish', '1');
        $notice = Setting::get('notice', '');

        $checks = [
            ["Clé API fournisseur (SUPPLIER_API_KEY)", !empty(env('SUPPLIER_API_KEY'))],
            ["Jeton API pawaPay (PAWAPAY_API_TOKEN)", !empty(env('PAWAPAY_API_TOKEN'))],
            ["Envoi d'e-mails (MAIL_HOST)", !empty(env('MAIL_HOST'))],
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
            'usdRate' => 'required|numeric|min:100|max:2000',
            'marginPercent' => 'required|numeric|min:0|max:500',
            'minMarginXof' => 'required|numeric|min:0|max:50000',
            'roundingStep' => 'required|numeric|min:1|max:5000',
            'autoPublish' => 'nullable|boolean',
            'notice' => 'nullable|string|max:300',
        ]);

        Setting::set('usd_rate', (string) $validated['usdRate']);
        Setting::set('default_margin_percent', (string) $validated['marginPercent']);
        Setting::set('min_margin_xof', (string) $validated['minMarginXof']);
        Setting::set('rounding_step', (string) $validated['roundingStep']);
        Setting::set('auto_publish', $request->has('autoPublish') ? '1' : '0');
        Setting::set('notice', $validated['notice'] ?? '');

        AuditLog::record('settings.update', 'system', $validated);

        return back()->with('success', 'Paramètres de la boutique enregistrés avec succès.');
    }
}
