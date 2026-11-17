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
        $usdRate = Setting::get('usd_rate', '620');
        $marginPercent = Setting::get('default_margin_percent', '25');
        $maintenance = Setting::get('maintenance_mode', '0');

        return view('admin.settings.index', compact('usdRate', 'marginPercent', 'maintenance'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'usd_rate' => 'required|numeric|min:100|max:2000',
            'default_margin_percent' => 'required|numeric|min:0|max:200',
            'maintenance_mode' => 'nullable|boolean',
        ]);

        Setting::set('usd_rate', (string) $validated['usd_rate']);
        Setting::set('default_margin_percent', (string) $validated['default_margin_percent']);
        Setting::set('maintenance_mode', $request->has('maintenance_mode') ? '1' : '0');

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'settings.update',
            'target_type' => 'settings',
            'target_id' => 'system',
            'meta' => $validated,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        return back()->with('success', 'Paramètres mis à jour avec succès.');
    }
}
