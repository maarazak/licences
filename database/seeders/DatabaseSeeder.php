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
        // 1. Données indispensables en tout environnement (Production & Local)
        $this->call(PermissionSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(SettingSeeder::class);
        $this->call(AdminSeeder::class);

        // 2. Données de démonstration : environnement LOCAL et TESTING UNIQUEMENT
        // Ne s'exécutera JAMAIS en production
        if (app()->environment('local', 'testing')) {
            $this->call(CustomerSeeder::class);
            $this->call(ProductSeeder::class);
        }

        // 3. Nettoyage des caches applicatifs
        Artisan::call('cache:clear');
        Artisan::call('permission:cache-reset');

        $this->userInfo();
    }

    /**
     * Affiche un récapitulatif clair adapté à l'environnement.
     */
    private function userInfo(): void
    {
        $env = app()->environment();
        $this->command->line('');
        $this->command->info('================================================================');
        $this->command->info("  Licences & Logiciels — Base de données initialisée ({$env})");
        $this->command->info('================================================================');
        $this->command->line('');
        $this->command->warn('Compte Administrateur Backoffice (Blade) :');
        $this->command->line(' - URL : /admin/connexion');
        $this->command->line(' - E-mail : ' . env('ADMIN_EMAIL', 'abdoulrazakmaali@gmail.com'));
        $this->command->line('');

        if (app()->environment('local', 'testing')) {
            $this->command->warn('Données de démonstration chargées (LOCAL / TESTING) :');
            $this->command->line(' - Client démo : test@example.com / password (Solde: 25 000 FCFA)');
            $this->command->line(' - Admin démo : admin@licences.local / password');
            $this->command->line(' - Catalogue démo : 4 produits de test créés');
            $this->command->line('');
        } else {
            $this->command->comment('Mode PRODUCTION : Aucune donnée fictive (client démo, faux produits) n\'a été insérée.');
            $this->command->comment('Utilisez [php artisan catalog:sync] pour alimenter le catalogue officiel via Flowseller.');
            $this->command->line('');
        }

        $this->command->info('================================================================');
        $this->command->line('');
    }
}
