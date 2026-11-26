<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** `migrate:fresh --seed` doit donner un site prêt : droits, réglages, administrateur, publication automatique. */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_seed_complet_prepare_le_site(): void
    {
        Setting::clearRuntimeCache();
        $this->seed(DatabaseSeeder::class);
        Setting::clearRuntimeCache();

        // Réglages de prix
        $this->assertSame('669', Setting::get('usdRate'));
        $this->assertSame('100', Setting::get('marginPercent'));
        $this->assertSame('1000', Setting::get('minMarginXof'));
        $this->assertSame('250', Setting::get('roundingStep'));
        // Publication automatique active, quelle que soit la clé lue
        $this->assertTrue(Setting::flag('autoPublish', 'auto_publish'));
        $this->assertSame('1', Setting::get('auto_publish'));

        // Droits et administrateur
        $this->assertTrue(Role::where(['name' => 'admin', 'guard_name' => 'admin'])->exists());
        $admin = User::where('email', 'abdoulrazakmaali@gmail.com')->firstOrFail();
        $this->assertTrue(Hash::check('Admin@2026', $admin->password));
        $this->assertTrue($admin->hasRole('admin', 'admin'));
    }

    public function test_le_seed_est_relancable_sans_doublon(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('email', 'abdoulrazakmaali@gmail.com')->count());
        $this->assertSame(1, Role::where(['name' => 'admin', 'guard_name' => 'admin'])->count());
    }
}
