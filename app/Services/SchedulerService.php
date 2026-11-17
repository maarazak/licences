<?php

namespace App\Services;

use App\Models\LoginCode;
use App\Models\RateLimit;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SchedulerService
{
    public const MIN_SYNC_GAP_SECONDS = 300;

    protected OrderFulfillmentService $fulfillment;
    protected CatalogService $catalog;
    protected StockAlertService $alerts;
    protected ReminderService $reminders;

    public function __construct(
        OrderFulfillmentService $fulfillment,
        CatalogService $catalog,
        StockAlertService $alerts,
        ReminderService $reminders
    ) {
        $this->fulfillment = $fulfillment;
        $this->catalog = $catalog;
        $this->alerts = $alerts;
        $this->reminders = $reminders;
    }

    /**
     * Tâche d'une minute :
     * 1. orders : rattrape les paiements, reprend les livraisons, suit les activations
     * 2. catalog : synchronise le catalogue depuis Flowseller
     * 3. balance : relit le solde revendeur en USD
     * 4. alerts : envoie les alertes des offres revenues en stock
     * 5. reminders : relances de paiement
     * 6. purge : purge les données périmées (codes de connexion, sessions, rate limits)
     */
    public function runTick(): array
    {
        // Verrou atomique 55 secondes
        $lockKey = 'cron_tick_lock';
        $lock = Cache::lock($lockKey, 55);

        if (!$lock->get()) {
            return ['skipped' => 'Exécution précédente en cours'];
        }

        $report = [];

        try {
            // 1. Orders maintenance
            try {
                $report['orders'] = $this->fulfillment->runOrderMaintenance();
            } catch (\Throwable $e) {
                Log::error("[CronTick] Erreur orders: {$e->getMessage()}");
                $report['orders'] = ['error' => $e->getMessage()];
            }

            // 2. Catalog sync (avec respect du gap minimum de 5 minutes et vérification de cooldown)
            try {
                $rateLimitedUntil = (int) Setting::get('flowseller_rate_limited_until', 0);
                $inCooldown = time() < $rateLimitedUntil;

                $lastSync = Setting::get('lastCatalogSyncAt');
                $shouldSync = !$inCooldown;
                if ($lastSync && $shouldSync) {
                    $lastTime = strtotime($lastSync);
                    if ($lastTime && (time() - $lastTime < self::MIN_SYNC_GAP_SECONDS)) {
                        $shouldSync = false;
                        $report['catalog'] = 'à jour (< 5 min)';
                    }
                }

                if ($shouldSync) {
                    $report['catalog'] = $this->catalog->syncCatalog();
                } elseif ($inCooldown) {
                    $report['catalog'] = 'temporisation fournisseur (' . ($rateLimitedUntil - time()) . 's)';
                }
            } catch (\Throwable $e) {
                Log::error("[CronTick] Erreur catalog: {$e->getMessage()}");
                Setting::set('catalogSyncError', mb_substr($e->getMessage(), 0, 300));
                $report['catalog'] = ['error' => $e->getMessage()];
            }

            // 3. Balance refresh (toutes les 5 minutes et pas en cooldown)
            try {
                $rateLimitedUntil = (int) Setting::get('flowseller_rate_limited_until', 0);
                $inCooldown = time() < $rateLimitedUntil;

                $lastBal = Setting::get('lastSupplierBalanceCheckAt');
                $shouldCheckBal = !$inCooldown;
                if ($lastBal && $shouldCheckBal) {
                    $lastBalTime = strtotime($lastBal);
                    if ($lastBalTime && (time() - $lastBalTime < 300)) {
                        $shouldCheckBal = false;
                        $report['balance'] = 'à jour (< 5 min)';
                    }
                }

                if ($shouldCheckBal) {
                    $this->catalog->refreshSupplierBalance();
                    $report['balance'] = 'ok';
                }
            } catch (\Throwable $e) {
                $report['balance'] = ['error' => $e->getMessage()];
            }

            // 4. Stock alerts
            try {
                $report['alerts'] = $this->alerts->processStockAlerts();
            } catch (\Throwable $e) {
                $report['alerts'] = ['error' => $e->getMessage()];
            }

            // 5. Payment reminders
            try {
                $report['reminders'] = $this->reminders->processReminders();
            } catch (\Throwable $e) {
                $report['reminders'] = ['error' => $e->getMessage()];
            }

            // 6. Purge
            try {
                $purgedCodes = LoginCode::where('expires_at', '<', now())->delete();
                $purgedLimits = RateLimit::where('reset_at', '<', now())->delete();
                $report['purge'] = [
                    'login_codes' => $purgedCodes,
                    'rate_limits' => $purgedLimits,
                ];
            } catch (\Throwable $e) {
                $report['purge'] = ['error' => $e->getMessage()];
            }
        } finally {
            $lock->release();
        }

        return $report;
    }
}
