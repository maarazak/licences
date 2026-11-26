<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletEntry;
use App\Services\CinetPayService;
use App\Services\NowPaymentsService;
use App\Services\OrderFulfillmentService;
use App\Services\WalletDepositService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

    /**
     * Notification CinetPay. C'est un simple SIGNAL : on ne croit rien de son contenu.
     *  1. Les identifiants reçus ne servent qu'à retrouver l'enregistrement (correspondances exactes).
     *  2. Si un jeton de notification a été mémorisé à la création du paiement, il doit correspondre (hash_equals).
     *  3. L'état est relu chez CinetPay avec l'identifiant STOCKÉ de l'enregistrement (jamais celui de la requête) : un
     *     paiement valide ne peut donc pas être « rejoué » sur une autre commande ou une autre recharge.
     */
    public function cinetpay(Request $request)
    {
        $payload = $request->all();
        if (empty($payload)) {
            $payload = json_decode($request->getContent(), true) ?? [];
        }
        // Le jeton de notification est un secret : il n'est pas écrit dans les journaux.
        Log::info('[Webhook CinetPay] Notification reçue', Arr::except($payload, ['notify_token', 'token']));

        $merchantId = trim((string) ($payload['merchant_transaction_id'] ?? $payload['cpm_trans_id'] ?? ''));
        $transactionId = trim((string) ($payload['transaction_id'] ?? ''));
        $notifyToken = (string) ($payload['notify_token'] ?? '');

        if ($merchantId === '' && $transactionId === '') {
            Log::warning('[Webhook CinetPay] Aucun identifiant de transaction fourni.');
            return response()->json(['error' => 'Missing transaction identifier'], 400);
        }

        $order = $this->findByGatewayIds(Order::class, $merchantId, $transactionId);
        $deposit = $order ? null : $this->findByGatewayIds(WalletDeposit::class, $merchantId, $transactionId);
        $record = $order ?? $deposit;

        if (!$record) {
            Log::info('[Webhook CinetPay] Aucun enregistrement correspondant.');
            return response()->json(['status' => 'not_found'], 404);
        }

        $stored = (string) ($record->notify_token ?? '');
        if ($stored !== '' && !hash_equals($stored, $notifyToken)) {
            Log::warning("[Webhook CinetPay] Jeton de notification invalide pour {$record->reference}.");
            return response()->json(['error' => 'Invalid notify token'], 400);
        }

        $queryKey = $record->payment_intent_id ?: $record->reference;
        $state = $this->cinetPay->getPayment($queryKey, $record->customer_country);
        if (!empty($state['error'])) {
            // CinetPay réessaiera la notification ; la tâche planifiée rattrape aussi les paiements en attente.
            Log::warning("[Webhook CinetPay] Relecture impossible pour {$record->reference}: {$state['error']}");
            return response()->json(['status' => 'retry_later'], 503);
        }

        if ($order) {
            app(OrderFulfillmentService::class)->settleGatewayPayment($order, $state, 'cinetpay');
            return response()->json(['status' => 'order_synced']);
        }

        app(WalletDepositService::class)->settleGatewayPayment($deposit, $state);
        return response()->json(['status' => 'deposit_synced']);
    }

    /** Retrouve un enregistrement par identifiant marchand (prioritaire) puis par identifiant de transaction — exact uniquement. */
    protected function findByGatewayIds(string $model, string $merchantId, string $transactionId)
    {
        if ($merchantId !== '') {
            $found = $model::where(fn ($q) => $q->where('payment_intent_id', $merchantId)->orWhere('reference', $merchantId))->first();
            if ($found) {
                return $found;
            }
        }

        return $transactionId !== '' ? $model::where('payment_checkout_id', $transactionId)->first() : null;
    }

    /**
     * IPN NOWPayments (crypto-monnaies). Simple SIGNAL : signature HMAC vérifiée, enregistrement retrouvé par correspondance
     * exacte de la référence, puis état RELU chez NOWPayments avec la clé API avant tout changement.
     */
    public function nowpayments(Request $request, NowPaymentsService $nowPayments)
    {
        if (!$nowPayments->verifyWebhookSignature($request->getContent(), (string) $request->header('x-nowpayments-sig', ''))) {
            Log::warning('[Webhook NOWPayments] Signature invalide.');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = $request->all();
        Log::info('[Webhook NOWPayments] IPN reçu', Arr::only($payload, ['payment_id', 'order_id', 'payment_status']));

        $orderRef = (string) ($payload['order_id'] ?? '');
        $paymentId = (string) ($payload['payment_id'] ?? '');
        if ($orderRef === '' || !ctype_digit($paymentId)) {
            return response()->json(['error' => 'Missing order_id or payment_id'], 400);
        }

        $order = Order::where('reference', $orderRef)->where('paid_with', 'nowpayments')->first();
        $deposit = $order ? null : WalletDeposit::where('reference', $orderRef)->where('gateway', 'nowpayments')->first();
        $record = $order ?? $deposit;
        if (!$record) {
            Log::info("[Webhook NOWPayments] Aucun enregistrement pour {$orderRef}.");
            return response()->json(['status' => 'not_found'], 404);
        }

        // Mémorise l'identifiant de paiement : il permet de relire l'état au retour du client.
        $record->update(['payment_checkout_id' => $paymentId]);

        $verified = $nowPayments->getPayment($paymentId);
        if (!empty($verified['error'])) {
            Log::warning("[Webhook NOWPayments] Relecture impossible pour {$orderRef}: {$verified['error']}");
            return response()->json(['status' => 'retry_later'], 503); // NOWPayments réessaiera
        }

        if ($order) {
            app(OrderFulfillmentService::class)->settleCryptoPayment($order, $verified, 'nowpayments');
            return response()->json(['status' => 'order_synced']);
        }

        app(WalletDepositService::class)->settleCryptoPayment($deposit, $verified);
        return response()->json(['status' => 'deposit_synced']);
    }
}
