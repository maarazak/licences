<?php

namespace App\Services;

use App\Models\Setting;
use Cryptomus\Api\Client;
use Cryptomus\Api\Payment;
use Cryptomus\Api\RequestBuilderException;
use Exception;
use Illuminate\Support\Facades\Log;

class CryptomusService
{
    protected string $merchantId;
    protected string $paymentKey;
    protected ?string $payoutKey;
    protected ?Payment $paymentClient = null;

    public function __construct()
    {
        $this->merchantId = (string) config('services.cryptomus.merchant_id', env('CRYPTOMUS_MERCHANT_ID', ''));
        $this->paymentKey = (string) config('services.cryptomus.payment_key', env('CRYPTOMUS_PAYMENT_KEY', ''));
        $this->payoutKey = config('services.cryptomus.payout_key', env('CRYPTOMUS_PAYOUT_KEY'));

        if ($this->isConfigured()) {
            $this->paymentClient = Client::payment($this->paymentKey, $this->merchantId);
        }
    }

    /**
     * Indique si Cryptomus est configuré avec les identifiants marchands.
     */
    public function isConfigured(): bool
    {
        return !empty($this->merchantId) && !empty($this->paymentKey);
    }

    /**
     * Retourne l'instance Payment du SDK officiel Cryptomus.
     */
    public function client(): Payment
    {
        if (!$this->isConfigured() || !$this->paymentClient) {
            throw new Exception("Cryptomus n'est pas configuré. Veuillez renseigner CRYPTOMUS_MERCHANT_ID et CRYPTOMUS_PAYMENT_KEY.");
        }

        return $this->paymentClient;
    }

    /**
     * Vérifie la signature d'un webhook entrant de Cryptomus.
     */
    public function verifyWebhookSignature(array $payload): bool
    {
        if (empty($payload['sign'])) {
            return false;
        }

        $receivedSign = (string) $payload['sign'];
        $cleanData = $payload;
        unset($cleanData['sign']);

        $json = json_encode($cleanData, JSON_UNESCAPED_UNICODE);
        $expectedSign = md5(base64_encode($json) . $this->paymentKey);

        return hash_equals($expectedSign, $receivedSign);
    }

    /**
     * Convertit un montant en FCFA vers USD (formaté à 2 décimales).
     */
    public function convertXofToUsd(int $amountXof): string
    {
        $usdRate = (int) Setting::get('usdRate', Setting::get('usd_rate', 669));
        if ($usdRate <= 0) {
            $usdRate = 669;
        }

        $amountUsd = $amountXof / $usdRate;
        // Minimum 0.50 USD pour respecter les limites du réseau
        $amountUsd = max(0.50, round($amountUsd, 2));

        return number_format($amountUsd, 2, '.', '');
    }

    /**
     * Crée une facture de paiement chez Cryptomus via le SDK officiel.
     *
     * @param array{
     *     order_id: string,
     *     amount_xof: int,
     *     return_url: string,
     *     callback_url?: string,
     *     is_payment_multiple?: bool,
     *     lifetime?: string
     * } $params
     * @return array
     */
    public function createPayment(array $params): array
    {
        $amountUsd = $this->convertXofToUsd($params['amount_xof']);
        $callbackUrl = $params['callback_url'] ?? route('webhooks.cryptomus');

        $data = [
            'amount' => $amountUsd,
            'currency' => 'USD',
            'order_id' => $params['order_id'],
            'url_return' => $params['return_url'],
            'url_callback' => $callbackUrl,
            'is_payment_multiple' => (bool) ($params['is_payment_multiple'] ?? false),
            'lifetime' => (string) ($params['lifetime'] ?? '3600'), // 1 heure
        ];

        Log::info("[Cryptomus] Création facture pour {$params['order_id']} ({$params['amount_xof']} FCFA = {$amountUsd} USD)");

        try {
            $result = $this->client()->create($data);

            return [
                'uuid' => $result['uuid'] ?? null,
                'order_id' => $result['order_id'] ?? $params['order_id'],
                'amount_usd' => $amountUsd,
                'payment_url' => $result['url'] ?? null,
                'status' => $result['status'] ?? $result['payment_status'] ?? 'check',
                'expired_at' => $result['expired_at'] ?? null,
            ];
        } catch (RequestBuilderException $e) {
            Log::error("[Cryptomus] Erreur création paiement sur méthode {$e->getMethod()}: {$e->getMessage()}");
            throw new Exception("Erreur de paiement Cryptomus : " . $e->getMessage());
        }
    }

    /**
     * Récupère l'état d'un paiement chez Cryptomus par order_id ou uuid.
     */
    public function getPaymentInfo(string $orderId, ?string $uuid = null): array
    {
        $data = ['order_id' => $orderId];
        if ($uuid) {
            $data['uuid'] = $uuid;
        }

        try {
            return $this->client()->info($data);
        } catch (RequestBuilderException $e) {
            Log::error("[Cryptomus] Erreur récupération paiement {$orderId}: {$e->getMessage()}");
            throw new Exception("Erreur consultation Cryptomus : " . $e->getMessage());
        }
    }

    /**
     * Récupère la liste des services et devises supportées chez Cryptomus.
     */
    public function getServices(): array
    {
        try {
            return $this->client()->services();
        } catch (RequestBuilderException $e) {
            Log::error("[Cryptomus] Erreur consultation services: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Récupère le solde du compte marchand Cryptomus.
     */
    public function getBalance(): array
    {
        try {
            return $this->client()->balance();
        } catch (RequestBuilderException $e) {
            Log::error("[Cryptomus] Erreur consultation solde: {$e->getMessage()}");
            return [];
        }
    }
}
