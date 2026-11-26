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
        }

        // 3. Premier import du catalogue fournisseur, sans attendre le cron. Non bloquant : une panne du fournisseur
        // n'empêche pas l'initialisation (le cron réessaie ensuite toutes les 5 minutes).
        $this->importCatalog();

        // 4. Nettoyage des caches applicatifs
        Artisan::call('cache:clear');
        Artisan::call('permission:cache-reset');

        $this->userInfo();
    }

    private function importCatalog(): void
    {
        if (app()->environment('testing') || !app(\App\Services\FlowsellerService::class)->isConfigured()) {
            $this->command->comment('Catalogue : import ignoré (fournisseur non configuré). Le cron ou « php artisan catalog:sync » l\'importera.');

            return;
        }

        try {
            $catalog = app(\App\Services\CatalogService::class);
            $report = $catalog->syncCatalog();
            $catalog->refreshSupplierBalance();
            $this->command->info("Catalogue importé : {$report['created']} produit(s) créé(s) sur {$report['total']} reçus (publiés automatiquement).");
        } catch (\Throwable $e) {
            $this->command->warn('Catalogue : import impossible pour le moment (' . mb_substr($e->getMessage(), 0, 120) . '). Le cron réessaiera.');
        }
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
        $this->command->line(' - E-mail : ' . 'admin@local.com');
        $this->command->line('');

        if (app()->environment('local', 'testing')) {
            $this->command->warn('Données de démonstration chargées (LOCAL / TESTING) :');
            $this->command->line(' - Client démo : test@example.com / password (Solde: 25 000 FCFA)');
            $this->command->line(' - Admin démo : admin@licences.local / password');
            $this->command->line(' - Les produits viennent du fournisseur (via php artisan catalog:sync ou le cron)');
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
