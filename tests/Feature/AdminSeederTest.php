<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Le compte administrateur est défini dans le seeder (aucune variable d'environnement), mot de passe stocké en empreinte. */
class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_seeder_cree_l_admin_sans_aucune_variable_d_environnement(): void
    {
        putenv('ADMIN_EMAIL');
        putenv('ADMIN_PASSWORD');
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'abdoulrazakmaali@gmail.com')->firstOrFail();
        $this->assertTrue(Hash::check('Admin@2026', $admin->password));
        $this->assertFalse(Hash::check('autre-mot-de-passe', $admin->password));
        $this->assertTrue($admin->hasRole('admin', 'admin'));
        $this->assertStringNotContainsString('Admin@2026', file_get_contents(database_path('seeders/AdminSeeder.php')), 'pas de mot de passe en clair dans le code');
    }

    public function test_relancer_le_seeder_remet_le_mot_de_passe_du_seeder_sans_doublon(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AdminSeeder::class);
        User::where('email', 'abdoulrazakmaali@gmail.com')->update(['password' => Hash::make('modifie')]);

        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::where('email', 'abdoulrazakmaali@gmail.com')->count());
        $this->assertTrue(Hash::check('Admin@2026', User::where('email', 'abdoulrazakmaali@gmail.com')->first()->password));
    }
}
