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
            'usd_rate' => '620',
            'default_margin_percent' => '25',
            'support_email' => 'support@licences.local',
            'site_name' => 'Licences & Logiciels',
        ];

        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }
    }
}
