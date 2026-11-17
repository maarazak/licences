<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderFulfillmentService
{
    public const LOCK_MINUTES = 5;
    public const MAX_ATTEMPTS = 5;

    protected FlowsellerService $flowseller;
    protected CryptoService $crypto;
    protected PawaPayService $pawaPay;

    public function __construct(FlowsellerService $flowseller, CryptoService $crypto, PawaPayService $pawaPay)
    {
        $this->flowseller = $flowseller;
        $this->crypto = $crypto;
        $this->pawaPay = $pawaPay;
    }

    /**
     * Lance ou reprend l'achat automatique chez le revendeur Flowseller.
     */
    public function fulfil(string $orderId): bool
    {
        $lockThreshold = now()->subMinutes(self::LOCK_MINUTES);

        // Prise de verrou atomique
        $affected = Order::where('id', $orderId)
            ->where(function ($query) use ($lockThreshold) {
                $query->where('status', 'paid')
                    ->orWhere(function ($q) use ($lockThreshold) {
                        $q->where('status', 'processing')
                            ->where(function ($sub) use ($lockThreshold) {
                                $sub->whereNull('fulfil_locked_at')
                                    ->orWhere('fulfil_locked_at', '<', $lockThreshold);
                            });
                    });
            })
            ->update([
                'status' => 'processing',
                'fulfil_locked_at' => now(),
                'fulfil_attempts' => DB::raw('fulfil_attempts + 1'),
                'updated_at' => now(),
            ]);

        if ($affected !== 1) {
            return false;
        }

        $order = Order::find($orderId);
        if (!$order) {
            return false;
        }

        // Si le produit n'a pas de fournisseur externe configuré, livraison directe ou manuelle
        if (!$order->supplier_product_id) {
            $order->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'fulfil_locked_at' => null,
            ]);
            OrderEvent::create([
                'id' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'type' => 'delivered',
                'message' => 'Commande livrée (produit local direct).',
                'actor' => 'system',
            ]);
            return true;
        }

        // Si Flowseller n'est pas configuré, marquer en attente d'activation manuelle
        if (!$this->flowseller->isConfigured()) {
            $order->update([
                'status' => 'activation_awaiting',
                'fulfil_locked_at' => null,
            ]);
            OrderEvent::create([
                'id' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'type' => 'activation_awaiting',
                'message' => 'En attente d\'activation manuelle (clé API fournisseur non configurée).',
                'actor' => 'system',
            ]);
            return true;
        }

        // Idempotency key stable
        if (!$order->supplier_idem_key) {
            $order->update(['supplier_idem_key' => Str::uuid()->toString()]);
            $order->refresh();
        }

        try {
            $result = $this->flowseller->createOrder(
                $order->supplier_product_id,
                $order->quantity,
                $order->activation_emails ?? null,
                $order->supplier_idem_key
            );

            $this->applySupplierOrder($order, $result);
            return true;
        } catch (FlowsellerException $e) {
            Log::warning("[Fulfillment] Erreur fournisseur pour {$order->reference}: {$e->getMessage()}");

            if ($e->isDefinitive()) {
                $hasEarlierRetry = OrderEvent::where('order_id', $order->id)
                    ->where('type', 'fulfil_retry')
                    ->exists();

                $code = $hasEarlierRetry ? 'uncertain_outcome' : $e->codeStr;
                $message = $hasEarlierRetry
                    ? "Un essai précédent a une issue inconnue; dernière réponse : {$e->codeStr} — {$e->getMessage()}"
                    : $e->getMessage();

                $this->markFailed($order, $code, $message);
                return false;
            }

            if ($order->fulfil_attempts >= self::MAX_ATTEMPTS) {
                $this->markFailed($order, 'too_many_attempts', "Abandon après {$order->fulfil_attempts} essais. Dernière erreur : {$e->getMessage()}");
                return false;
            }

            // Retry ultérieur
            OrderEvent::create([
                'id' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'type' => 'fulfil_retry',
                'message' => "Livraison à reprendre (essai {$order->fulfil_attempts}) : {$e->getMessage()}",
                'actor' => 'system',
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error("[Fulfillment] Exception inattendue pour {$order->reference}: {$e->getMessage()}");
            if ($order->fulfil_attempts >= self::MAX_ATTEMPTS) {
                $this->markFailed($order, 'internal_error', $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Traite le résultat retourné par le revendeur Flowseller.
     */
    protected function applySupplierOrder(Order $order, array $result): void
    {
        $supplierOrderId = isset($result['id']) ? substr((string) $result['id'], 0, 64) : null;
        $supplierRef = isset($result['reference']) ? substr((string) $result['reference'], 0, 64) : null;
        $status = strtolower($result['status'] ?? 'pending');

        $base = [
            'supplier_order_id' => $supplierOrderId,
            'supplier_reference' => $supplierRef,
            'supplier_status' => $status,
            'fulfil_locked_at' => null,
            'updated_at' => now(),
        ];

        if ($status === 'completed' || $status === 'success') {
            $items = FlowsellerService::extractDeliveryItems($result);
            if (empty($items)) {
                $this->markFailed($order, 'empty_delivery', 'Commande fournisseur terminée sans accès ou code livré.', $base);
                return;
            }

            $encrypted = $this->crypto->encrypt(json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $order->id);

            $order->update(array_merge($base, [
                'status' => 'delivered',
                'delivery_encrypted' => $encrypted,
                'delivered_at' => now(),
                'failure_code' => null,
                'failure_message' => null,
            ]));

            OrderEvent::create([
                'id' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'type' => 'delivered',
                'message' => 'Accès et licences livrés (' . count($items) . ' élément(s)).',
                'actor' => 'supplier',
            ]);

            try {
                app(MailService::class)->sendOrderDelivered($order);
            } catch (\Throwable $e) {
                Log::warning("[Mail] Erreur notification livraison {$order->reference}: " . $e->getMessage());
            }
            return;
        }

        if ($status === 'activation_awaiting') {
            $order->update(array_merge($base, [
                'status' => 'activation_awaiting',
            ]));

            OrderEvent::create([
                'id' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'type' => 'activation_awaiting',
                'message' => 'Activation en attente chez le fournisseur.',
                'actor' => 'supplier',
            ]);

            try {
                app(MailService::class)->sendOrderActivation($order);
            } catch (\Throwable $e) {
                Log::warning("[Mail] Erreur notification activation {$order->reference}: " . $e->getMessage());
            }
            return;
        }

        if (in_array($status, ['cancelled', 'failed', 'refunded'], true)) {
            $this->markFailed($order, "supplier_{$status}", "Commande fournisseur {$status}.", $base);
            return;
        }

        // Statut intermédiaire
        $order->update(array_merge($base, [
            'status' => 'activation_awaiting',
        ]));

        OrderEvent::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'type' => 'supplier_pending',
            'message' => "Commande fournisseur en cours ({$status}).",
            'actor' => 'supplier',
        ]);
    }

    /**
     * Marque une commande en échec de livraison.
     */
    protected function markFailed(Order $order, string $code, string $message, array $extra = []): void
    {
        $order->update(array_merge($extra, [
            'status' => 'failed',
            'fulfil_locked_at' => null,
            'failure_code' => substr($code, 0, 64),
            'failure_message' => substr($message, 0, 500),
            'updated_at' => now(),
        ]));

        OrderEvent::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'type' => 'fulfil_failed',
            'message' => "Livraison impossible ({$code}) : {$message}",
            'actor' => 'system',
        ]);

        try {
            app(MailService::class)->sendOrderDelayed($order);
        } catch (\Throwable $e) {
            Log::warning("[Mail] Erreur notification retard {$order->reference}: " . $e->getMessage());
        }
    }

    /**
     * Sonde le statut d'une commande en cours d'activation chez le revendeur.
     */
    public function pollActivation(string $orderId): void
    {
        $order = Order::find($orderId);
        if (!$order || $order->status !== 'activation_awaiting' || !$order->supplier_order_id) {
            return;
        }

        try {
            $result = $this->flowseller->getOrder($order->supplier_order_id);
            if (strtolower($result['status'] ?? '') === 'activation_awaiting') {
                return;
            }
            $this->applySupplierOrder($order, $result);
        } catch (\Throwable $e) {
            Log::warning("[Activation Poll] Erreur suivi pour {$order->reference}: {$e->getMessage()}");
        }
    }

    /**
     * Synchronise le paiement pawaPay d'une commande.
     */
    public function syncPayment(string $orderId): ?Order
    {
        $order = Order::find($orderId);
        if (!$order || !in_array($order->status, ['pending_payment', 'expired', 'payment_failed'], true)) {
            return $order;
        }

        $depositId = $order->payment_checkout_id ?: $order->payment_intent_id;
        if (!$depositId) {
            return $order;
        }

        try {
            $state = $this->pawaPay->getDeposit($depositId);
            $paymentStatus = strtolower($state['payment_status'] ?? '');

            if ($paymentStatus === 'completed') {
                $amountOk = ($state['amount'] ?? 0) === $order->total_xof &&
                    (empty($state['currency']) || in_array(strtoupper($state['currency']), ['XOF', 'XAF'], true));

                if ($amountOk && $order->status !== 'paid' && $order->status !== 'delivered') {
                    $order->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'updated_at' => now(),
                    ]);

                    OrderEvent::create([
                        'id' => Str::uuid()->toString(),
                        'order_id' => $order->id,
                        'type' => 'paid',
                        'message' => 'Paiement confirmé par pawaPay.',
                        'actor' => 'pawapay',
                    ]);

                    // Déclenchement immédiat de la livraison
                    $this->fulfil($order->id);
                    return $order->fresh();
                }
            } elseif (in_array($paymentStatus, ['failed', 'expired'], true) && $order->status === 'pending_payment') {
                $newStatus = $paymentStatus === 'failed' ? 'payment_failed' : 'expired';
                $order->update([
                    'status' => $newStatus,
                    'failure_code' => $state['failure_code'] ?? $newStatus,
                    'failure_message' => substr($state['failure_message'] ?? 'Paiement non complété', 0, 500),
                    'updated_at' => now(),
                ]);

                OrderEvent::create([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $order->id,
                    'type' => $newStatus,
                    'message' => "Paiement pawaPay {$paymentStatus}.",
                    'actor' => 'pawapay',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("[Payment Sync] Erreur vérification pawaPay pour {$order->reference}: {$e->getMessage()}");
        }

        return $order->fresh();
    }

    /**
     * Convertit une commande en échec en avoir sur le solde client.
     */
    public function customerChooseCredit(string $userId, string $orderId): bool
    {
        $order = Order::where('id', $orderId)->where('user_id', $userId)->first();
        if (!$order || $order->status !== 'failed' || $order->refund_requested_at) {
            return false;
        }

        $done = DB::transaction(function () use ($order) {
            $locked = Order::lockForUpdate()->find($order->id);
            if ($locked->status !== 'failed' || $locked->refund_requested_at) {
                return false;
            }

            $user = \App\Models\User::lockForUpdate()->find($order->user_id);
            if (!$user) return false;

            $newBalance = $user->balance_xof + $order->total_xof;
            $user->balance_xof = $newBalance;
            $user->save();

            \App\Models\WalletEntry::create([
                'id' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'amount_xof' => $order->total_xof,
                'balance_after_xof' => $newBalance,
                'type' => 'credit',
                'kind' => 'order_credit',
                'description' => "Avoir — commande {$order->reference}",
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'actor' => 'client',
                'created_at' => now(),
            ]);

            $locked->update([
                'status' => 'refunded',
                'refund_method' => 'balance',
                'refunded_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });

        if ($done) {
            OrderEvent::create([
                'id' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'type' => 'credited',
                'message' => "{$order->total_xof} FCFA crédités sur le solde du client.",
                'actor' => 'client',
                'created_at' => now(),
            ]);

            try {
                app(MailService::class)->sendOrderCredited($order);
            } catch (\Throwable $e) {
                Log::warning("Erreur notification avoir : " . $e->getMessage());
            }

            return true;
        }

        return false;
    }

    /**
     * Le client demande un remboursement sur son moyen de paiement opérateur.
     */
    public function customerRequestRefund(string $userId, string $orderId): bool
    {
        $order = Order::where('id', $orderId)->where('user_id', $userId)->first();
        if (!$order || $order->status !== 'failed' || $order->paid_with !== 'pawapay' || $order->refund_requested_at) {
            return false;
        }

        $affected = Order::where('id', $order->id)
            ->where('status', 'failed')
            ->whereNull('refund_requested_at')
            ->update([
                'refund_requested_at' => now(),
                'updated_at' => now(),
            ]);

        if ($affected === 1) {
            OrderEvent::create([
                'id' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'type' => 'refund_requested',
                'message' => 'Le client demande un remboursement sur son moyen de paiement.',
                'actor' => 'client',
                'created_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Tâche de maintenance globale pour les commandes (cron / artisan).
     */
    public function runOrderMaintenance(): array
    {
        $report = ['payments' => 0, 'fulfilments' => 0, 'activations' => 0, 'expired' => 0];

        // 1. Rattrapage des paiements en attente depuis > 2 minutes
        $pending = Order::where('status', 'pending_payment')
            ->where('created_at', '<', now()->subMinutes(2))
            ->orderBy('updated_at')
            ->limit(15)
            ->get();

        foreach ($pending as $ord) {
            $this->syncPayment($ord->id);
            $ord->update(['updated_at' => now()]);
            $report['payments']++;
        }

        // 2. Expiration des commandes après 48h sans paiement
        $stale = Order::where('status', 'pending_payment')
            ->where('created_at', '<', now()->subHours(48))
            ->limit(20)
            ->get();

        foreach ($stale as $ord) {
            $fresh = $this->syncPayment($ord->id);
            if ($fresh && $fresh->status === 'pending_payment') {
                $fresh->update(['status' => 'expired', 'updated_at' => now()]);
                OrderEvent::create([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $fresh->id,
                    'type' => 'expired',
                    'message' => 'Commande close après 48 h sans confirmation de paiement.',
                    'actor' => 'system',
                ]);
                $report['expired']++;
            }
        }

        // 3. Commandes payées en attente de livraison
        $lockThreshold = now()->subMinutes(self::LOCK_MINUTES);
        $toFulfil = Order::where(function ($query) use ($lockThreshold) {
            $query->where('status', 'paid')
                ->orWhere(function ($q) use ($lockThreshold) {
                    $q->where('status', 'processing')
                        ->where(function ($sub) use ($lockThreshold) {
                            $sub->whereNull('fulfil_locked_at')
                                ->orWhere('fulfil_locked_at', '<', $lockThreshold);
                        });
                });
        })->limit(5)->get();

        foreach ($toFulfil as $ord) {
            $this->fulfil($ord->id);
            $report['fulfilments']++;
        }

        // 4. Activations bloquées depuis > 72 heures
        $stuck = Order::where('status', 'activation_awaiting')
            ->where('paid_at', '<', now()->subHours(72))
            ->limit(20)
            ->get();

        foreach ($stuck as $ord) {
            $this->markFailed($ord, 'activation_timeout', 'Activation non terminée par le fournisseur après 72 h.');
        }

        // 5. Suivi des activations en cours
        $awaiting = Order::where('status', 'activation_awaiting')
            ->orderBy('updated_at')
            ->limit(15)
            ->get();

        foreach ($awaiting as $ord) {
            $this->pollActivation($ord->id);
            $ord->update(['updated_at' => now()]);
            $report['activations']++;
        }

        return $report;
    }
}
