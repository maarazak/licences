<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\ProductStockItem;
use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderFulfillmentService
{
    public const LOCK_MINUTES = 5;
    public const MAX_ATTEMPTS = 5;
    /** États d'une commande dont le paiement peut encore aboutir (y compris un paiement tardif après expiration). */
    public const PAYABLE_STATUSES = ['pending_payment', 'expired', 'payment_failed'];

    protected FlowsellerService $flowseller;
    protected CryptoService $crypto;
    protected CinetPayService $cinetPay;

    public function __construct(FlowsellerService $flowseller, CryptoService $crypto, CinetPayService $cinetPay)
    {
        $this->flowseller = $flowseller;
        $this->crypto = $crypto;
        $this->cinetPay = $cinetPay;
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

        // Produit manuel : livré depuis le stock propre, sans aucun appel au fournisseur.
        $product = $order->product_id ? Product::find($order->product_id) : null;
        if ($product && $product->isManual()) {
            return $this->deliverFromOwnStock($order, $product);
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

        // Si le service externe n'est pas configuré, marquer en attente d'activation manuelle
        if (!$this->flowseller->isConfigured()) {
            $order->update([
                'status' => 'activation_awaiting',
                'fulfil_locked_at' => null,
            ]);
            OrderEvent::create([
                'id' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'type' => 'activation_awaiting',
                'message' => 'Commande en cours de préparation par notre équipe technique.',
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
                'message' => "Activation en cours de traitement par notre équipe technique.",
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
     * Livre une commande d'un produit manuel en prenant ses accès dans le stock propre. Prise ATOMIQUE (lignes verrouillées,
     * passage « disponible » → « vendu » conditionnel) : un même accès ne peut jamais être livré à deux clients. Rejouable :
     * si les accès de cette commande ont déjà été pris (arrêt en cours de route), ce sont les mêmes qui sont livrés.
     */
    protected function deliverFromOwnStock(Order $order, Product $product): bool
    {
        try {
            $plain = DB::transaction(function () use ($order, $product) {
                $mine = ProductStockItem::where('order_id', $order->id)->where('status', 'sold')->get();
                $items = $mine->count() >= $order->quantity
                    ? $mine->take($order->quantity)
                    : ProductStockItem::where('product_id', $product->id)
                        ->where('status', 'available')
                        ->orderBy('created_at')->orderBy('id')
                        ->limit($order->quantity)
                        ->lockForUpdate()
                        ->get();

                if ($items->count() < $order->quantity) {
                    return null; // stock insuffisant
                }

                $contents = [];
                foreach ($items as $item) {
                    $value = $item->plain();
                    if ($value === null || $value === '') {
                        throw new \RuntimeException("Accès de stock illisible ({$item->id}).");
                    }
                    $contents[] = $value;
                }

                if ($mine->count() < $order->quantity) {
                    $moved = ProductStockItem::whereIn('id', $items->pluck('id'))->where('status', 'available')->update([
                        'status' => 'sold', 'order_id' => $order->id, 'sold_at' => now(), 'updated_at' => now(),
                    ]);
                    if ($moved !== $items->count()) {
                        throw new \RuntimeException('Conflit de stock : accès pris par une autre commande.');
                    }
                }

                return $contents;
            });
        } catch (\Throwable $e) {
            Log::error("[Fulfillment] Stock propre : erreur pour {$order->reference}: {$e->getMessage()}");
            $this->markFailed($order, 'internal_error', 'Erreur lors de la prise dans le stock : ' . $e->getMessage());
            $product->refreshManualStock();

            return false;
        }

        if ($plain === null) {
            $this->markFailed($order, 'insufficient_stock', 'Stock insuffisant pour livrer cette commande.');
            $product->refreshManualStock();

            return false;
        }

        $order->update([
            'status' => 'delivered',
            'delivery_encrypted' => $this->crypto->encrypt(json_encode($plain, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $order->id),
            'delivered_at' => now(),
            'fulfil_locked_at' => null,
            'failure_code' => null,
            'failure_message' => null,
            'updated_at' => now(),
        ]);
        $product->refreshManualStock();

        OrderEvent::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'type' => 'delivered',
            'message' => 'Accès livrés depuis le stock (' . count($plain) . ' élément(s)).',
            'actor' => 'system',
        ]);

        try {
            app(MailService::class)->sendOrderDelivered($order);
        } catch (\Throwable $e) {
            Log::warning("[Mail] Erreur notification livraison {$order->reference}: " . $e->getMessage());
        }

        return true;
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
                'message' => 'Votre licence est en cours d\'activation sur la plateforme.',
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
            'message' => "Activation en cours sur nos serveurs.",
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
            'message' => "Commande prise en charge par notre équipe pour régularisation.",
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
     * Synchronise le paiement CinetPay d'une commande : relit l'état chez CinetPay avec l'identifiant STOCKÉ de la commande
     * (jamais un identifiant venu d'une requête), puis applique le résultat.
     */
    public function syncPayment(string $orderId): ?Order
    {
        $order = Order::find($orderId);
        if (!$order || !in_array($order->status, self::PAYABLE_STATUSES, true)) {
            return $order;
        }

        $depositId = $order->payment_intent_id ?: $order->payment_checkout_id ?: $order->reference ?: $order->id;
        if (!$depositId) {
            return $order;
        }

        try {
            $state = $this->cinetPay->getPayment($depositId, $order->customer_country);
            if (empty($state['error'])) {
                $this->settleGatewayPayment($order, $state, 'payment_gateway');
            }
        } catch (\Throwable $e) {
            Log::warning("[Payment Sync] Erreur vérification CinetPay pour {$order->reference}: {$e->getMessage()}");
        }

        return $order->fresh();
    }

    /**
     * Applique à UNE commande l'état d'un paiement CinetPay relu auprès de l'API (jamais le contenu d'un webhook).
     * Toutes les transitions sont atomiques (`UPDATE … WHERE status IN (…)`) : webhook, retour client, tâche planifiée et
     * vérification admin peuvent arriver en même temps, un seul passe. Un paiement tardif (commande expirée ou en échec de
     * paiement) est repris. Retourne : paid | failed | underpaid | ignored.
     */
    public function settleGatewayPayment(Order $order, array $state, string $actor = 'cinetpay'): string
    {
        // La réponse doit concerner CETTE commande (identifiants), sinon rien n'est modifié.
        if (!CinetPayService::matchesRecord($order, $state)) {
            Log::warning("[Payment] Réponse CinetPay sans correspondance avec la commande {$order->reference}.");
            return 'ignored';
        }

        if (!empty($state['is_successful'])) {
            if (CinetPayService::isUnderpaid((int) $order->total_xof, $state)) {
                $moved = Order::where('id', $order->id)->whereIn('status', self::PAYABLE_STATUSES)->update([
                    'status' => 'failed',
                    'paid_with' => 'cinetpay',
                    'paid_at' => now(),
                    'failure_code' => 'amount_mismatch',
                    'failure_message' => 'Montant ou devise reçu différent du montant dû : décision de l\'équipe requise.',
                    'updated_at' => now(),
                ]);
                if ($moved === 1) {
                    $this->logOrderEvent($order, 'amount_mismatch', 'Paiement reçu mais montant ou devise différent du montant dû.', $actor);
                    Log::error("[Payment] Montant inférieur au dû pour la commande {$order->reference}.");
                }
                return 'underpaid';
            }

            $moved = Order::where('id', $order->id)->whereIn('status', self::PAYABLE_STATUSES)->update([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_with' => 'cinetpay',
                'failure_code' => null,
                'failure_message' => null,
                'updated_at' => now(),
            ]);
            if ($moved !== 1) {
                return 'ignored'; // déjà traitée par un autre passage
            }

            $order->refresh();
            $this->logOrderEvent($order, 'paid', 'Paiement confirmé.', $actor);
            try {
                app(MailService::class)->sendOrderPaid($order);
            } catch (\Throwable $e) {
                Log::warning("[Payment] Erreur e-mail paiement {$order->reference}: {$e->getMessage()}");
            }
            // La livraison a son propre verrou atomique : un appel concurrent ne peut pas acheter deux fois.
            try {
                $this->fulfil($order->id);
            } catch (\Throwable $e) {
                Log::warning("[Payment] Erreur livraison {$order->reference}: {$e->getMessage()}");
            }
            return 'paid';
        }

        if (!empty($state['is_final'])) {
            $moved = Order::where('id', $order->id)->where('status', 'pending_payment')->update([
                'status' => 'payment_failed',
                'failure_code' => 'payment_failed',
                'failure_message' => "Paiement non complété ({$state['status']})",
                'updated_at' => now(),
            ]);
            if ($moved === 1) {
                $this->logOrderEvent($order, 'payment_failed', "Paiement {$state['status']}.", $actor);
            }
            return $moved === 1 ? 'failed' : 'ignored';
        }

        return 'ignored'; // encore en attente chez CinetPay
    }

    /**
     * Relit l'état du paiement crypto d'une commande chez NOWPayments (si l'identifiant de paiement est connu, c'est-à-dire
     * après un premier IPN) puis applique le résultat. Sans identifiant, l'IPN fera foi à son arrivée.
     */
    public function syncCryptoPayment(Order $order): Order
    {
        $paymentId = (string) $order->payment_checkout_id;
        if ($order->paid_with !== 'nowpayments' || !in_array($order->status, self::PAYABLE_STATUSES, true) || !ctype_digit($paymentId)) {
            return $order;
        }

        try {
            $verified = app(NowPaymentsService::class)->getPayment($paymentId);
            if (empty($verified['error'])) {
                $this->settleCryptoPayment($order, $verified, 'crypto');
            }
        } catch (\Throwable $e) {
            Log::warning("[Payment] Vérification NOWPayments impossible pour {$order->reference}: {$e->getMessage()}");
        }

        return $order->fresh() ?? $order;
    }

    /**
     * Applique à UNE commande l'état d'un paiement NOWPayments RELU chez NOWPayments (jamais le contenu brut d'un IPN).
     * Mêmes garanties que `settleGatewayPayment` : transitions atomiques, paiement tardif repris, un seul « payé » malgré
     * les doublons. `$verified` : réponse normalisée de `NowPaymentsService::getPayment`. Retourne : paid | failed | underpaid | ignored.
     */
    public function settleCryptoPayment(Order $order, array $verified, string $actor = 'nowpayments'): string
    {
        // La réponse doit concerner CETTE commande.
        if (($verified['order_id'] ?? '') === '' || !hash_equals((string) $order->reference, (string) $verified['order_id'])) {
            Log::warning("[Payment] Réponse NOWPayments sans correspondance avec la commande {$order->reference}.");
            return 'ignored';
        }

        $status = (string) ($verified['payment_status'] ?? '');

        if ($status === 'finished') {
            if (app(NowPaymentsService::class)->isUnderpaid((int) $order->total_xof, $verified)) {
                $moved = Order::where('id', $order->id)->whereIn('status', self::PAYABLE_STATUSES)->update([
                    'status' => 'failed',
                    'paid_with' => 'nowpayments',
                    'paid_at' => now(),
                    'failure_code' => 'amount_mismatch',
                    'failure_message' => 'Montant ou devise reçu différent du montant dû : décision de l\'équipe requise.',
                    'updated_at' => now(),
                ]);
                if ($moved === 1) {
                    $this->logOrderEvent($order, 'amount_mismatch', 'Paiement crypto reçu mais montant ou devise différent du montant dû.', $actor);
                    Log::error("[Payment] Montant crypto inférieur au dû pour la commande {$order->reference}.");
                }
                return 'underpaid';
            }

            $moved = Order::where('id', $order->id)->whereIn('status', self::PAYABLE_STATUSES)->update([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_with' => 'nowpayments',
                'failure_code' => null,
                'failure_message' => null,
                'updated_at' => now(),
            ]);
            if ($moved !== 1) {
                return 'ignored';
            }

            $order->refresh();
            $coin = ($verified['pay_currency'] ?? '') ?: 'Crypto';
            $network = !empty($verified['network']) ? " ({$verified['network']})" : '';
            $this->logOrderEvent($order, 'paid', "Paiement de {$order->total_xof} FCFA confirmé en crypto [{$coin}{$network}].", $actor);
            try {
                app(MailService::class)->sendOrderPaid($order);
            } catch (\Throwable $e) {
                Log::warning("[Payment] Erreur e-mail paiement {$order->reference}: {$e->getMessage()}");
            }
            try {
                $this->fulfil($order->id);
            } catch (\Throwable $e) {
                Log::warning("[Payment] Erreur livraison {$order->reference}: {$e->getMessage()}");
            }
            return 'paid';
        }

        if (in_array($status, ['failed', 'expired', 'refunded'], true)) {
            $moved = Order::where('id', $order->id)->where('status', 'pending_payment')->update([
                'status' => 'payment_failed',
                'failure_code' => 'payment_failed',
                'failure_message' => "Paiement crypto non finalisé ({$status})",
                'updated_at' => now(),
            ]);
            if ($moved === 1) {
                $this->logOrderEvent($order, 'payment_failed', "Paiement crypto {$status}.", $actor);
            }
            return $moved === 1 ? 'failed' : 'ignored';
        }

        return 'ignored'; // waiting, confirming, confirmed, sending, partially_paid : en cours
    }

    protected function logOrderEvent(Order $order, string $type, string $message, string $actor): void
    {
        OrderEvent::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'type' => $type,
            'event' => $type,
            'actor' => $actor,
            'message' => $message,
            'created_at' => now(),
        ]);
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
        if (!$order || $order->status !== 'failed' || !in_array($order->paid_with, ['cinetpay', 'pawapay', 'gateway'], true) || $order->refund_requested_at) {
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
