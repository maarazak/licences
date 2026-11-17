<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\FlowsellerService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncCatalogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'catalog:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise le catalogue de produits depuis l\'API revendeur Flowseller';

    /**
     * Execute the console command.
     */
    public function handle(FlowsellerService $flowseller): int
    {
        $this->info('Récupération du catalogue Flowseller...');

        if (!$flowseller->isConfigured()) {
            $this->warn('SUPPLIER_API_KEY non configuré. Synchronisation ignorée.');
            return Command::SUCCESS;
        }

        try {
            $res = $flowseller->listAllProducts();
            $products = $res['products'];
            $seenIds = $res['seenIds'];

            $this->info(count($products) . ' produit(s) récupéré(s) chez le fournisseur.');

            foreach ($products as $p) {
                $supplierId = (string) ($p['id'] ?? '');
                if (!$supplierId) continue;

                $slug = Str::slug($p['name'] ?? $supplierId);

                $product = Product::where('supplier_id', $supplierId)->first();
                if (!$product) {
                    $product = Product::where('slug', $slug)->first();
                }

                $costUsdCents = (int) round(($p['price'] ?? 0) * 100);
                $listUsdCents = isset($p['list_price']) ? (int) round($p['list_price'] * 100) : 0;

                $data = [
                    'supplier_id' => $supplierId,
                    'name' => $p['name'] ?? 'Produit sans nom',
                    'category' => $p['category'] ?? '',
                    'image_url' => $p['image_url'] ?? null,
                    'delivery_kind' => $p['delivery_kind'] ?? 'key',
                    'cost_usd_cents' => $costUsdCents,
                    'list_usd_cents' => $listUsdCents,
                    'stock' => isset($p['stock']) ? (int) $p['stock'] : null,
                    'in_stock' => (bool) ($p['in_stock'] ?? false),
                    'preorder' => (bool) ($p['preorder'] ?? false),
                    'expire_days' => (int) ($p['expire_days'] ?? 0),
                    'warranty' => $p['warranty'] ?? '',
                    'requires_email' => (bool) ($p['requires_email'] ?? false),
                    'supplier_purchasable' => (bool) ($p['purchasable'] ?? false),
                    'supplier_active' => true,
                    'synced_at' => now(),
                ];

                if (!$product) {
                    $data['id'] = Str::uuid()->toString();
                    $data['slug'] = $slug;
                    $data['display_name'] = $p['name'] ?? '';
                    $data['is_published'] = false;
                    Product::create($data);
                } else {
                    $product->update($data);
                }
            }

            // Désactiver les produits qui ne sont plus dans le catalogue fournisseur
            if (!empty($seenIds)) {
                Product::whereNotIn('supplier_id', $seenIds)->update(['supplier_active' => false]);
            }

            $this->info('Catalogue synchronisé avec succès.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erreur lors de la synchronisation : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
