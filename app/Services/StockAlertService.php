<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAlertService
{
    /**
     * Traite les alertes de retour en stock en attente.
     */
    public function processStockAlerts(): int
    {
        $pending = StockAlert::whereNull('notified_at')
            ->with(['product', 'user'])
            ->whereHas('product', function ($q) {
                $q->where('in_stock', true)
                    ->where('is_published', true)
                    ->where('supplier_active', true)
                    ->where('supplier_purchasable', true)
                    ->where('preorder', false)
                    ->where(function ($sub) {
                        $sub->whereNull('stock')->orWhere('stock', '>', 0);
                    });
            })
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();

        $sent = 0;
        $mailService = app(MailService::class);

        foreach ($pending as $alert) {
            $product = $alert->product;
            $user = $alert->user;

            if (!$product || !$user) {
                continue;
            }

            // Réservation atomique
            $claimed = StockAlert::where('id', $alert->id)
                ->whereNull('notified_at')
                ->update(['notified_at' => now()]);

            if ($claimed !== 1) {
                continue;
            }

            try {
                $mailService->sendStockAlert($alert);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("[StockAlertService] Échec envoi alerte stock: {$e->getMessage()}");
            }
        }

        return $sent;
    }
}
