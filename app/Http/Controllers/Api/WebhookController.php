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
        if (!$orderId && !$uuid) {
            Log::warning("[Webhook Cryptomus] Identifiant de commande ou UUID absent.");
            return response()->json(['error' => 'Missing order_id or uuid'], 400);
        }

        // Webhook signé : identifiants fiables, mais on retrouve l'enregistrement par correspondance exacte uniquement.
        $find = function (string $model) use ($orderId, $uuid) {
            if ($orderId !== '') {
                $found = $model::where(fn ($q) => $q->where('reference', $orderId)->orWhere('payment_intent_id', $orderId))->first();
                if ($found) {
                    return $found;
                }
            }

            return $uuid !== '' ? $model::where('payment_checkout_id', $uuid)->first() : null;
        };

        $order = $find(Order::class);
        if ($order) {
            app(OrderFulfillmentService::class)->settleCryptoPayment($order, $payload, 'cryptomus');
            return response()->json(['status' => 'order_synced']);
        }

        $deposit = $find(WalletDeposit::class);
        if ($deposit) {
            app(WalletDepositService::class)->settleCryptoPayment($deposit, $payload);
            return response()->json(['status' => 'deposit_synced']);
        }

        Log::info("[Webhook Cryptomus] Aucun enregistrement trouvé pour order_id={$orderId}, uuid={$uuid}");
        return response()->json(['status' => 'not_found'], 404);
    }
}
