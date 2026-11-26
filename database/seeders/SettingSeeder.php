<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'usd_rate' => '669',
            'usdRate' => '669',
            'marginPercent' => '100',
            'default_margin_percent' => '100',
            'minMarginXof' => '1000',
            'min_margin_xof' => '1000',
            'roundingStep' => '250',
            'rounding_step' => '250',
            // Publication automatique des nouveaux produits vendables à chaque synchronisation (aucune étape manuelle).
            'autoPublish' => '1',
            'auto_publish' => '1',
            'support_email' => 'abdoulrazakmaali@gmail.com',
            'site_name' => 'Licences',
            'notice' => '',
            'lastCatalogSyncAt' => '',
            'catalogSyncError' => '',
            'supplierBalanceCents' => '0',
            'supplierBalanceAt' => '',
            'supplierBalanceError' => '',
        ];

        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }
    }
}
