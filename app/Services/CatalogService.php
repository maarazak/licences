<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CatalogService
{
    public const CONFIRM_UNAVAILABLE_READINGS = 5;

    protected FlowsellerService $flowseller;

    public function __construct(FlowsellerService $flowseller)
    {
        $this->flowseller = $flowseller;
    }

    /**
     * Synchronise le catalogue complet depuis l'API revendeur Flowseller vers la base locale.
     * Respecte l'idempotence et ne touche JAMAIS aux personnalisations admin
     * (is_published, shop_category, display_name, descriptions, instructions, prix fixes).
     */
    public function syncCatalog(bool $force = false): array
    {
        if (!$this->flowseller->isConfigured()) {
            return ['skipped' => 'Fournisseur non configuré'];
        }

        // Évite le déclenchement en rafale si un sync a réussi il y a moins de 15s
        if (!$force) {
            $lastSync = Setting::get('lastCatalogSyncAt');
            if ($lastSync) {
                $diff = time() - strtotime($lastSync);
                if ($diff < 15 && Product::count() > 0) {
                    return [
                        'total' => Product::count(),
                        'created' => 0,
                        'updated' => 0,
                        'unchanged' => Product::count(),
                        'deactivated' => 0,
                        'held' => 0,
                        'cached' => true,
                    ];
                }
            }
        }

        $now = now();
        $autoPublish = Setting::get('autoPublish') === 'true';

        $remote = $this->flowseller->listAllProducts();
        $products = $remote['products'];
        $seenIds = $remote['seenIds'];

        $existing = Product::all()->keyBy('supplier_id');
        $existingSlugs = Product::pluck('slug')->all();
        $slugSet = array_flip($existingSlugs);

        $report = [
            'total' => count($products),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'deactivated' => 0,
            'held' => 0,
        ];

        foreach ($products as $item) {
            $supplierId = (string) ($item['id'] ?? '');
            if (!$supplierId) {
                continue;
            }

            $price = (float) ($item['price'] ?? 0);
            $sellable = $price > 0;
            $costUsdCents = (int) round($price * 100);
            $listUsdCents = isset($item['list_price']) ? (int) round(((float) $item['list_price']) * 100) : $costUsdCents;

            $stock = isset($item['stock']) && $item['stock'] !== null && $item['stock'] !== '' ? (int) $item['stock'] : null;
            $inStock = (bool) ($item['in_stock'] ?? false);
            $preorder = (bool) ($item['preorder'] ?? false);
            $purchasable = (bool) ($item['purchasable'] ?? false);

            $fields = [
                'name' => mb_substr($item['name'] ?? 'Produit sans nom', 0, 255),
                'category' => mb_substr($item['category'] ?? '', 0, 100),
                'image_url' => isset($item['image_url']) && filter_var($item['image_url'], FILTER_VALIDATE_URL) ? mb_substr($item['image_url'], 0, 1024) : null,
                'delivery_kind' => mb_substr($item['delivery_kind'] ?? 'key', 0, 64),
                'supplier_description' => isset($item['description']) && trim($item['description']) ? mb_substr(trim($item['description']), 0, 20000) : null,
                'cost_usd_cents' => $costUsdCents,
                'list_usd_cents' => $listUsdCents,
                'stock' => $stock,
                'in_stock' => $sellable ? $inStock : false,
                'preorder' => $preorder,
                'expire_days' => (int) ($item['expire_days'] ?? 0),
                'warranty' => mb_substr($item['warranty'] ?? '', 0, 100),
                'requires_email' => (bool) ($item['requires_email'] ?? false),
                'supplier_purchasable' => $sellable ? $purchasable : false,
                'supplier_active' => true,
                'synced_at' => $now,
            ];

            $known = $existing->get($supplierId);

            if ($known) {
                // Gestion de la confirmation de dégradation
                $previouslySellable = $known->supplier_purchasable && $known->in_stock && !$known->preorder && ($known->stock === null || $known->stock > 0);
                $currentlySellable = $fields['supplier_purchasable'] && $fields['in_stock'] && !$fields['preorder'] && ($fields['stock'] === null || $fields['stock'] > 0);

                $held = false;
                if ($previouslySellable && !$currentlySellable && ($known->unavailable_readings + 1 < self::CONFIRM_UNAVAILABLE_READINGS)) {
                    // Conserver temporairement l'état vendable pour amortir les micro-coupures fournisseur
                    $fields['supplier_purchasable'] = $known->supplier_purchasable;
                    $fields['in_stock'] = $known->in_stock;
                    $fields['stock'] = $known->stock;
                    $fields['unavailable_readings'] = $known->unavailable_readings + 1;
                    $held = true;
                } else {
                    $fields['unavailable_readings'] = 0;
                }

                $known->update($fields);
                if ($held) {
                    $report['held']++;
                }
                $report['updated']++;
                continue;
            }

            if (!$sellable) {
                continue;
            }

            // Génération d'un slug unique
            $baseSlug = Str::slug($item['slug'] ?? ($item['name'] ?? $supplierId));
            $slug = $baseSlug ?: 'produit';
            if (isset($slugSet[$slug])) {
                $slug = "{$baseSlug}-" . substr(Str::slug($supplierId), -8);
            }
            if (isset($slugSet[$slug])) {
                $slug = "{$baseSlug}-" . Str::random(8);
            }
            $slugSet[$slug] = true;

            $fields['id'] = Str::uuid()->toString();
            $fields['supplier_id'] = $supplierId;
            $fields['slug'] = $slug;
            $fields['display_name'] = $item['name'] ?? '';
            $fields['is_published'] = $autoPublish;
            $fields['unavailable_readings'] = 0;
            $fields['created_at'] = $now;
            $fields['updated_at'] = $now;

            Product::create($fields);
            $report['created']++;
        }

        // Marquer les produits absents du fournisseur comme inactifs
        if (!empty($seenIds)) {
            $deactivatedCount = Product::whereNotIn('supplier_id', $seenIds)
                ->where('supplier_active', true)
                ->update(['supplier_active' => false, 'updated_at' => $now]);
            $report['deactivated'] = $deactivatedCount;
        }

        Setting::set('lastCatalogSyncAt', $now->toIso8601String());
        Setting::set('catalogSyncError', '');

        return $report;
    }

    /**
     * Rafraîchit le solde revendeur en USD et l'enregistre dans les réglages système.
     */
    public function refreshSupplierBalance(): void
    {
        if (!$this->flowseller->isConfigured()) {
            return;
        }

        try {
            $balanceData = $this->flowseller->getBalance();
            $balance = (float) ($balanceData['balance'] ?? 0);
            $cents = (int) round($balance * 100);

            Setting::set('supplierBalanceCents', (string) $cents);
            Setting::set('supplierBalanceAt', now()->toIso8601String());
            Setting::set('supplierBalanceError', '');
        } catch (\Throwable $e) {
            Setting::set('supplierBalanceError', mb_substr($e->getMessage(), 0, 300));
            Log::warning("[CatalogService] Erreur rafraîchissement solde fournisseur: {$e->getMessage()}");
        }
    }
}
