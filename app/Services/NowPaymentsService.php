<?php

namespace App\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Paiements en crypto-monnaies via NOWPayments (factures hébergées : USDT, Bitcoin, Ethereum, Solana, TON, Litecoin…).
 * Un IPN (webhook) n'est qu'un signal : sa signature est vérifiée, puis l'état est RELU chez NOWPayments avec la clé API.
 */
class NowPaymentsService
{
    /** Tolérance sur le montant facturé : l'administrateur peut ajuster le taux du dollar entre la facture et l'IPN. */
    private const AMOUNT_TOLERANCE = 0.95;

    private string $apiKey;
    private string $ipnSecret;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.nowpayments.api_key', '');
        $this->ipnSecret = (string) config('services.nowpayments.ipn_secret', '');
        $this->baseUrl = rtrim((string) config('services.nowpayments.base_url', 'https://api.nowpayments.io/v1'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->ipnSecret !== '';
    }

    /** Montant en dollars (2 décimales) correspondant à un prix en FCFA, au taux du dollar de la boutique. */
    public function convertXofToUsd(int $amountXof): float
    {
        $usdRate = (int) Setting::get('usdRate', Setting::get('usd_rate', 669));
        if ($usdRate <= 0) {
            $usdRate = 669;
        }

        // Minimum 0,50 $ : en dessous, la plupart des réseaux crypto refusent la transaction.
        return max(0.50, round($amountXof / $usdRate, 2));
    }

    /**
     * Crée une facture hébergée.
     *
     * @param array{order_id:string, amount_xof:int, return_url:string, description?:string} $params
     */
    public function createInvoice(array $params): array
    {
        if (!$this->isConfigured()) {
            throw new Exception("NOWPayments n'est pas configuré (NOWPAYMENTS_API_KEY et NOWPAYMENTS_IPN_SECRET).");
        }

        $amountUsd = $this->convertXofToUsd((int) $params['amount_xof']);

        Log::info("[NOWPayments] Création facture pour {$params['order_id']} ({$params['amount_xof']} FCFA = {$amountUsd} USD)");

        $response = Http::withHeaders(['x-api-key' => $this->apiKey])
            ->timeout(20)
            ->acceptJson()
            ->post("{$this->baseUrl}/invoice", [
                'price_amount' => $amountUsd,
                'price_currency' => 'usd',
                'order_id' => $params['order_id'],
                'order_description' => substr((string) ($params['description'] ?? "Commande {$params['order_id']}"), 0, 200),
                'ipn_callback_url' => route('webhooks.nowpayments'),
                'success_url' => $params['return_url'],
                'cancel_url' => $params['return_url'],
            ]);

        if (!$response->successful() || empty($response->json('invoice_url'))) {
            Log::error('[NOWPayments] Échec création facture', ['status' => $response->status(), 'order_id' => $params['order_id']]);
            throw new Exception('Erreur de paiement crypto : facture non créée.');
        }

        return [
            'invoice_id' => (string) $response->json('id'),
            'payment_url' => $response->json('invoice_url'),
            'order_id' => $response->json('order_id') ?? $params['order_id'],
            'amount_usd' => $amountUsd,
        ];
    }

    /**
     * État vérifié d'un paiement (identifiant de paiement NOWPayments). En cas d'erreur réseau : clé `error`.
     */
    public function getPayment(string $paymentId): array
    {
        try {
            $response = Http::withHeaders(['x-api-key' => $this->apiKey])
                ->timeout(20)
                ->acceptJson()
                ->get("{$this->baseUrl}/payment/" . rawurlencode($paymentId));

            if (!$response->successful()) {
                return ['error' => "HTTP {$response->status()}"];
            }

            $data = (array) $response->json();

            return [
                'payment_id' => (string) ($data['payment_id'] ?? $paymentId),
                'order_id' => (string) ($data['order_id'] ?? ''),
                'payment_status' => strtolower((string) ($data['payment_status'] ?? '')),
                'price_amount' => isset($data['price_amount']) ? (float) $data['price_amount'] : null,
                'price_currency' => strtolower((string) ($data['price_currency'] ?? '')),
                'pay_currency' => strtoupper((string) ($data['pay_currency'] ?? '')),
                'network' => (string) ($data['network'] ?? ''),
            ];
        } catch (\Throwable $e) {
            Log::warning("[NOWPayments] Échec consultation paiement {$paymentId}: " . $e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Signature IPN : HMAC-SHA512 du corps JSON dont les clés sont triées (méthode documentée par NOWPayments).
     * Le corps brut est aussi accepté s'il a déjà été envoyé trié. Comparaison à temps constant.
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $signature = strtolower(trim($signature));
        if ($this->ipnSecret === '' || $signature === '') {
            return false;
        }

        $candidates = [$rawBody];
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            $candidates[] = json_encode(self::sortKeys($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $candidates[] = json_encode(self::sortKeys($decoded));
        }

        foreach ($candidates as $body) {
            if (is_string($body) && hash_equals(hash_hmac('sha512', $body, $this->ipnSecret), $signature)) {
                return true;
            }
        }

        return false;
    }

    /** Tri récursif des clés (les listes gardent leur ordre). */
    private static function sortKeys(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sortKeys($value);
            }
        }
        if (!array_is_list($data)) {
            ksort($data);
        }

        return $data;
    }

    /** Le montant facturé relu chez NOWPayments couvre-t-il le prix attendu (en FCFA) ? Devise : dollar uniquement. */
    public function isUnderpaid(int $expectedXof, array $verified): bool
    {
        if (($verified['price_currency'] ?? '') !== 'usd' || !isset($verified['price_amount'])) {
            return true;
        }

        return (float) $verified['price_amount'] + 0.01 < $this->convertXofToUsd($expectedXof) * self::AMOUNT_TOLERANCE;
    }
}
