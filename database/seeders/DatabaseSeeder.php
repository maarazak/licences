<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(SettingSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(ProductSeeder::class);

        // Nettoyage des caches applicatifs
        Artisan::call('cache:clear');
        Artisan::call('permission:cache-reset');

        $this->userInfo();
    }

    /**
     * Affiche un récapitulatif clair des comptes créés en console.
     */
    private function userInfo(): void
    {
        $this->command->line('');
        $this->command->info('================================================================');
        $this->command->info('  Licences & Logiciels — Base de données initialisée avec succès');
        $this->command->info('================================================================');
        $this->command->line('');
        $this->command->warn('Compte Administrateur Backoffice (Blade) :');
        $this->command->line(' - URL de connexion : /admin/connexion');
        $this->command->line(' - E-mail : abdoulrazakmaali@gmail.com');
        $this->command->line(' - Mot de passe : Admin123!');
        $this->command->line(' - Compte de secours : admin@licences.local / password');
        $this->command->line('');
        $this->command->warn('Compte Client Boutique (Vue 3 / Inertia) :');
        $this->command->line(' - URL de connexion : /connexion');
        $this->command->line(' - E-mail : test@example.com');
        $this->command->line(' - Mot de passe : password');
        $this->command->line(' - Solde de test crédité : 25 000 FCFA');
        $this->command->line('');
        $this->command->info('================================================================');
        $this->command->line('');
    }
}
