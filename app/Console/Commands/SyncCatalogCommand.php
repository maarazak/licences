<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Setting;
use App\Services\CatalogService;
use Illuminate\Console\Command;

class SyncCatalogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'catalog:sync {--publish : Publier automatiquement tous les produits vendables après synchronisation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise le catalogue de produits depuis l\'API revendeur Flowseller';

    /**
     * Execute the console command.
     */
    public function handle(CatalogService $catalog): int
    {
        $this->info('Synchronisation du catalogue Flowseller en cours...');

        try {
            $report = $catalog->syncCatalog();

            $this->info("Synchronisation terminée : {$report['total']} reçus, {$report['created']} créés, {$report['updated']} mis à jour.");

            if ($this->option('publish') || Setting::flag('autoPublish', 'auto_publish')) {
                $count = Product::where('supplier_active', true)
                    ->where('source', '!=', 'manual')
                    ->where('supplier_purchasable', true)
                    ->where('preorder', false)
                    ->where('is_published', false)
                    ->update(['is_published' => true, 'updated_at' => now()]);
                $this->info("{$count} produit(s) vendable(s) ont été publiés.");
            }

            $publishedTotal = Product::where('is_published', true)->count();
            $this->info("Total sur la boutique : {$publishedTotal} produit(s) publié(s).");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Erreur de synchronisation : " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
