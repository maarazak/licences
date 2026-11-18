<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletEntry;
use App\Services\CinetPayService;
use App\Services\CryptomusService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    protected CinetPayService $cinetPay;

    public function __construct(CinetPayService $cinetPay)
    {
        $this->cinetPay = $cinetPay;
    }

    public function cinetpay(Request $request)
    {
        Log::info("[Webhook CinetPay] Payload reçu", $request->all());

        $payload = $request->all();
        if (empty($payload)) {
            $payload = json_decode($request->getContent(), true) ?? [];
        }

        $merchantTransId = $payload['merchant_transaction_id'] ?? $payload['cpm_trans_id'] ?? $request->input('merchant_transaction_id');
        $transactionId = $payload['transaction_id'] ?? $request->input('transaction_id');
        $notifyToken = $payload['notify_token'] ?? $request->input('notify_token');

        $queryId = $merchantTransId ?: $transactionId;

        if (!$queryId) {
            Log::warning("[Webhook CinetPay] Aucun identifiant de transaction fourni.");
            return response()->json(['error' => 'Missing transaction identifier'], 400);
        }

        // Relecture sécurisée auprès de l'API CinetPay
        try {
            $state = $this->cinetPay->getPayment($queryId);
        } catch (Exception $e) {
            Log::error("[Webhook CinetPay] Relecture échouée pour {$queryId}: {$e->getMessage()}");
            return response()->json(['status' => 'retry_later', 'message' => $e->getMessage()], 500);
        }

        // 1. Recherche dans les commandes
        $order = Order::where('payment_intent_id', $merchantTransId)
            ->orWhere('payment_checkout_id', $transactionId)
            ->orWhere('payment_intent_id', $queryId)
            ->orWhere('reference', $merchantTransId)
            ->orWhere('id', $queryId)
            ->first();

        if ($order) {
            $this->syncOrder($order, $state);
            return response()->json(['status' => 'order_synced']);
        }

        // 2. Recherche dans les recharges de solde
        $deposit = WalletDeposit::where('payment_intent_id', $merchantTransId)
            ->orWhere('payment_checkout_id', $transactionId)
            ->orWhere('payment_intent_id', $queryId)
            ->orWhere('reference', $merchantTransId)
            ->orWhere('id', $queryId)
            ->first();

        if ($deposit) {
            $this->syncDeposit($deposit, $state);
            return response()->json(['status' => 'deposit_synced']);
        }

        Log::info("[Webhook CinetPay] Aucun enregistrement trouvé pour {$queryId}");
        return response()->json(['status' => 'not_found'], 404);
    }

    /**
     * Webhook officiel Cryptomus pour les paiements en cryptomonnaies.
     */
    public function cryptomus(Request $request, CryptomusService $cryptomus)
    {
        Log::info("[Webhook Cryptomus] Requête reçue", $request->all());

        $payload = $request->all();
        if (empty($payload)) {
            $payload = json_decode($request->getContent(), true) ?? [];
        }

        if (!$cryptomus->verifyWebhookSignature($payload)) {
            Log::warning("[Webhook Cryptomus] Signature de webhook invalide.");
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $orderId = (string) ($payload['order_id'] ?? '');
        $uuid = (string) ($payload['uuid'] ?? '');
        $status = strtolower((string) ($payload['status'] ?? ''));

        if (!$orderId && !$uuid) {
            Log::warning("[Webhook Cryptomus] Identifiant de commande ou UUID absent.");
            return response()->json(['error' => 'Missing order_id or uuid'], 400);
        }

        $isPaid = in_array($status, ['paid', 'paid_over'], true);
        $isFailed = in_array($status, ['cancel', 'fail', 'system_fail'], true);

        // 1. Recherche dans les commandes
        $order = Order::where('reference', $orderId)
            ->orWhere('id', $orderId)
            ->orWhere('payment_checkout_id', $uuid)
            ->orWhere('payment_intent_id', $orderId)
            ->first();

        if ($order) {
            $this->syncCryptomusOrder($order, $payload, $isPaid, $isFailed);
            return response()->json(['status' => 'order_synced']);
        }

        // 2. Recherche dans les recharges de solde
        $deposit = WalletDeposit::where('reference', $orderId)
            ->orWhere('id', $orderId)
            ->orWhere('payment_checkout_id', $uuid)
            ->orWhere('payment_intent_id', $orderId)
            ->first();

        if ($deposit) {
            $this->syncCryptomusDeposit($deposit, $payload, $isPaid, $isFailed);
            return response()->json(['status' => 'deposit_synced']);
        }

        Log::info("[Webhook Cryptomus] Aucun enregistrement trouvé pour order_id={$orderId}, uuid={$uuid}");
        return response()->json(['status' => 'not_found'], 404);
    }

    protected function syncCryptomusOrder(Order $order, array $payload, bool $isPaid, bool $isFailed): void
    {
        if ($order->status === 'delivered' || $order->status === 'refunded') {
            return;
        }

        $cryptoCurrency = $payload['currency'] ?? 'Crypto';
        $payerCurrency = $payload['payer_currency'] ?? $cryptoCurrency;
        $network = $payload['network'] ?? '';

        if ($isPaid) {
            if ($order->status === 'pending_payment') {
                $order->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'paid_with' => 'cryptomus',
                    'payment_checkout_id' => $payload['uuid'] ?? $order->payment_checkout_id,
                    'updated_at' => now(),
                ]);

                $netInfo = $network ? " ({$network})" : '';
                OrderEvent::create([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $order->id,
                    'type' => 'paid',
                    'event' => 'paid',
                    'actor' => 'cryptomus',
                    'message' => "Paiement de {$order->total_xof} FCFA confirmé en crypto [{$payerCurrency}{$netInfo}].",
                    'created_at' => now(),
                ]);

                // Notification et lancement de la livraison automatique
                try {
                    app(\App\Services\MailService::class)->sendOrderPaid($order);
                    app(\App\Services\OrderFulfillmentService::class)->fulfil($order->id);
                } catch (\Throwable $e) {
                    Log::warning("[Webhook Cryptomus] Erreur fulfillment commande {$order->reference}: {$e->getMessage()}");
                }
            }
        } elseif ($isFailed && $order->status === 'pending_payment') {
            $order->update([
                'status' => 'cancelled',
                'failure_code' => 'payment_failed',
                'failure_message' => "Paiement crypto non finalisé ({$payload['status']})",
                'updated_at' => now(),
            ]);
        }
    }

    protected function syncCryptomusDeposit(WalletDeposit $deposit, array $payload, bool $isPaid, bool $isFailed): void
    {
        if ($deposit->status === 'completed' && $deposit->credited_at) {
            return;
        }

        $cryptoCurrency = $payload['currency'] ?? 'Crypto';
        $payerCurrency = $payload['payer_currency'] ?? $cryptoCurrency;
        $network = $payload['network'] ?? '';

        if ($isPaid) {
            DB::transaction(function () use ($deposit, $payload, $payerCurrency, $network) {
                $locked = WalletDeposit::lockForUpdate()->find($deposit->id);
                if (!$locked || $locked->credited_at) {
                    return;
                }

                $user = User::lockForUpdate()->find($deposit->user_id);
                if ($user) {
                    $newBalance = $user->balance_xof + $deposit->amount_xof;
                    $user->balance_xof = $newBalance;
                    $user->save();

                    $netInfo = $network ? " ({$network})" : '';
                    WalletEntry::create([
                        'id' => Str::uuid()->toString(),
                        'user_id' => $user->id,
                        'amount_xof' => $deposit->amount_xof,
                        'balance_after_xof' => $newBalance,
                        'type' => 'credit',
                        'kind' => 'deposit',
                        'description' => "Recharge Crypto [{$payerCurrency}{$netInfo}] — {$deposit->reference}",
                        'reference_type' => 'wallet_deposit',
                        'reference_id' => $deposit->id,
                        'created_at' => now(),
                    ]);
                }

                $locked->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'credited_at' => now(),
                    'payment_checkout_id' => $payload['uuid'] ?? $locked->payment_checkout_id,
                    'updated_at' => now(),
                ]);
            });
        } elseif ($isFailed && $deposit->status === 'pending') {
            $deposit->update([
                'status' => 'failed',
                'failure_message' => "Paiement crypto non complété ({$payload['status']})",
                'updated_at' => now(),
            ]);
        }
    }

    protected function syncOrder(Order $order, array $state): void
    {
        if ($order->status === 'delivered' || $order->status === 'refunded') {
            return;
        }

        if ($state['payment_status'] === 'completed' || $state['is_successful']) {
            if ($order->status === 'pending_payment') {
                $order->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'paid_with' => 'cinetpay',
                    'updated_at' => now(),
                ]);

                OrderEvent::create([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $order->id,
                    'type' => 'paid',
                    'event' => 'paid',
                    'actor' => 'cinetpay',
                    'message' => "Paiement de {$order->total_xof} FCFA confirmé.",
                    'created_at' => now(),
                ]);

                // Notification et lancement de la livraison automatique
                try {
                    app(\App\Services\MailService::class)->sendOrderPaid($order);
                    app(\App\Services\OrderFulfillmentService::class)->fulfil($order->id);
                } catch (\Throwable $e) {
                    Log::warning("[Webhook CinetPay] Erreur fulfillment commande {$order->reference}: {$e->getMessage()}");
                }
            }
        } elseif ($state['payment_status'] === 'failed' && $order->status === 'pending_payment') {
            $order->update([
                'status' => 'cancelled',
                'failure_code' => 'payment_failed',
                'failure_message' => "Paiement non validé ({$state['status']})",
                'updated_at' => now(),
            ]);
        }
    }

    protected function syncDeposit(WalletDeposit $deposit, array $state): void
    {
        if ($deposit->status === 'completed' && $deposit->credited_at) {
            return;
        }

        if ($state['payment_status'] === 'completed' || $state['is_successful']) {
            DB::transaction(function () use ($deposit) {
                $locked = WalletDeposit::lockForUpdate()->find($deposit->id);
                if (!$locked || $locked->credited_at) {
                    return;
                }

                $user = User::lockForUpdate()->find($deposit->user_id);
                if ($user) {
                    $newBalance = $user->balance_xof + $deposit->amount_xof;
                    $user->balance_xof = $newBalance;
                    $user->save();

                    WalletEntry::create([
                        'id' => Str::uuid()->toString(),
                        'user_id' => $user->id,
                        'amount_xof' => $deposit->amount_xof,
                        'balance_after_xof' => $newBalance,
                        'type' => 'credit',
                        'kind' => 'deposit',
                        'description' => "Recharge de solde — {$deposit->reference}",
                        'reference_type' => 'wallet_deposit',
                        'reference_id' => $deposit->id,
                        'created_at' => now(),
                    ]);
                }

                $locked->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'credited_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } elseif ($state['payment_status'] === 'failed' && $deposit->status === 'pending') {
            $deposit->update([
                'status' => 'failed',
                'failure_message' => "Paiement non complété ({$state['status']})",
                'updated_at' => now(),
            ]);
        }
    }
}
