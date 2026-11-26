<?php

namespace App\Services;

use App\Models\Order;
use CinetPay\CinetPay;
use CinetPay\Country;
use CinetPay\Currency;
use CinetPay\Language;
use CinetPay\Request\CreatePaymentRequest;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CinetPayService
{
    /**
     * Seules les devises à parité fixe avec le FCFA sont acceptées : XOF et XAF valent 1 pour 1. Pour GNF ou CDF, le même
     * nombre représenterait une valeur très différente (15 000 GNF ≈ 15 fois moins que 15 000 FCFA).
     */
    public const PAYABLE_CURRENCIES = ['XOF', 'XAF'];

    protected ?string $apiKey;
    protected ?string $apiPassword;
    protected string $environment;
    protected string $defaultCountry;
    protected string $defaultCurrency;

    public function __construct()
    {
        $this->apiKey = config('cinetpay.api_key') ?: env('CINETPAY_API_KEY');
        $this->apiPassword = config('cinetpay.api_password') ?: env('CINETPAY_API_PASSWORD');
        $this->environment = config('cinetpay.environment', 'sandbox');
        $this->defaultCountry = strtoupper(config('cinetpay.default_country', 'BF'));
        $this->defaultCurrency = strtoupper(config('cinetpay.default_currency', 'XOF'));
    }

    /**
     * Instancie le client CinetPay officiel pour le pays concerné.
     */
    public function getClient(?Country $country = null): CinetPay
    {
        if (empty($this->apiKey) || empty($this->apiPassword)) {
            throw new Exception("Les identifiants CinetPay (CINETPAY_API_KEY et CINETPAY_API_PASSWORD) ne sont pas configurés dans votre fichier .env.");
        }

        $targetCountry = $country ?? $this->resolveCountry($this->defaultCountry);

        $tokenStore = new CinetPayTokenStore();

        if ($this->environment === 'production') {
            return CinetPay::production($this->apiKey, $this->apiPassword, $targetCountry, null, $tokenStore);
        }

        return CinetPay::sandbox($this->apiKey, $this->apiPassword, $targetCountry, null, $tokenStore);
    }

    /**
     * Convertit un code pays ISO (BF, CI, SN, etc.) en enum CinetPay\Country.
     */
    public function resolveCountry(?string $code): Country
    {
        if (!$code) {
            return Country::BurkinaFaso;
        }

        $clean = strtoupper(trim($code));
        return Country::tryFrom($clean) ?? match ($clean) {
            'BFA' => Country::BurkinaFaso,
            'CIV' => Country::IvoryCoast,
            'SEN' => Country::Senegal,
            'MLI' => Country::Mali,
            'BEN' => Country::Benin,
            'TGO' => Country::Togo,
            'GIN' => Country::Guinea,
            'CMR' => Country::Cameroon,
            'NER' => Country::Niger,
            'COD' => Country::CongoKinshasa,
            'COG' => Country::CongoBrazzaville,
            'GAB' => Country::Gabon,
            default => Country::BurkinaFaso,
        };
    }

    /**
     * Formate et valide strictement un numéro de téléphone au format E.164 compatible avec le pays CinetPay.
     * En cas d'incohérence, renvoie null pour laisser le client renseigner son numéro sur la page sécurisée.
     */
    public static function formatE164Phone(?string $phone, Country $country): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $clean = preg_replace('/[^0-9]/', '', preg_replace('/^00/', '', trim($phone)));
        if (empty($clean)) {
            return null;
        }

        $callingDigits = ltrim($country->callingCode(), '+');

        // Si le numéro ne contient pas encore l'indicatif mais a la bonne longueur locale (ex: 8 chiffres pour BF)
        if (!str_starts_with($clean, $callingDigits) && strlen($clean) <= 10) {
            $clean = $callingDigits . $clean;
        }

        $formatted = '+' . $clean;

        // Validation stricte E.164 et concordance obligatoire avec l'indicatif du compte pays
        if (preg_match('/^\+[1-9][0-9]{7,14}$/', $formatted) === 1 && str_starts_with($formatted, $country->callingCode())) {
            return $formatted;
        }

        return null;
    }

    /**
     * Découpe un nom complet en prénom et nom pour respecter la validation CinetPay (min 2 car).
     */
    public static function splitName(?string $fullName, ?string $first = null, ?string $last = null): array
    {
        $f = trim($first ?? '');
        $l = trim($last ?? '');

        if (strlen($f) < 2 || strlen($l) < 2) {
            $parts = preg_split('/\s+/', trim($fullName ?? ''));
            if (!empty($parts[0]) && strlen($parts[0]) >= 2) {
                $f = $parts[0];
                $l = isset($parts[1]) && strlen(implode(' ', array_slice($parts, 1))) >= 2
                    ? implode(' ', array_slice($parts, 1))
                    : 'Client';
            } else {
                $f = 'Client';
                $l = 'Client';
            }
        }

        return [
            substr($f, 0, 50),
            substr($l, 0, 50),
        ];
    }

    /**
     * Initialise une session de paiement hébergée CinetPay (Cartes Bancaires + Mobile Money).
     *
     * @param array $input [
     *   'order_id' => ...,
     *   'reference' => ...,
     *   'amount_xof' => ...,
     *   'country' => 'BF',
     *   'email' => ...,
     *   'return_url' => ...,
     *   'description' => ...,
     *   'fullName' => ...,
     *   'phone' => ...,
     * ]
     * @return array
     */
    public function createPaymentPage(array $input): array
    {
        $countryEnum = $this->resolveCountry($input['country'] ?? null);
        if (!in_array($countryEnum->currency()->value, self::PAYABLE_CURRENCIES, true)) {
            throw new Exception("Ce pays n'est pas pris en charge pour le paiement en FCFA.");
        }
        $client = $this->getClient($countryEnum);

        // Identifiant de transaction marchand : 1 à 30 caractères alphanumériques
        $rawRef = $input['reference'] ?? $input['order_id'] ?? Str::random(12);
        $cleanRef = preg_replace('/[^a-zA-Z0-9_-]/', '', $rawRef);
        $merchantTransactionId = substr($cleanRef, 0, 30);
        if (strlen($merchantTransactionId) < 1) {
            $merchantTransactionId = 'TX' . strtoupper(Str::random(10));
        }

        $amount = (int) ($input['amount_xof'] ?? 0);
        if ($amount <= 0) {
            throw new Exception("Le montant du paiement doit être supérieur à zéro.");
        }

        // URLs (max 120 caractères selon spec SDK CinetPay)
        $returnUrl = substr($input['return_url'], 0, 120);
        $notifyUrl = substr(url('/api/webhooks/cinetpay'), 0, 120);
        $failedUrl = $returnUrl;

        // Informations client
        [$firstName, $lastName] = self::splitName(
            $input['fullName'] ?? $input['customer_name'] ?? null,
            $input['firstName'] ?? $input['first_name'] ?? null,
            $input['lastName'] ?? $input['last_name'] ?? null
        );

        $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: 'client@licences.local';
        $lang = ($input['lang'] ?? 'fr') === 'en' ? Language::English : Language::French;
        $designation = substr(trim($input['description'] ?? "Commande {$merchantTransactionId}"), 0, 100);

        $phone = self::formatE164Phone($input['phone'] ?? null, $countryEnum);

        $request = new CreatePaymentRequest(
            currency: $countryEnum->currency(),
            merchantTransactionId: $merchantTransactionId,
            amount: $amount,
            successUrl: $returnUrl,
            failedUrl: $failedUrl,
            notifyUrl: $notifyUrl,
            language: $lang,
            designation: $designation,
            clientFirstName: $firstName,
            clientLastName: $lastName,
            clientEmail: $email,
            clientPhoneNumber: $phone,
        );

        Log::info("[CinetPay] Initialisation paiement", [
            'merchant_transaction_id' => $merchantTransactionId,
            'amount' => $amount,
            'country' => $countryEnum->value,
            'currency' => $countryEnum->currency()->value,
        ]);

        $init = $client->payments()->create($request);

        return [
            'merchant_transaction_id' => $init->merchantTransactionId,
            'transaction_id' => $init->transactionId,
            'notify_token' => $init->notifyToken,
            'payment_token' => $init->paymentToken,
            'redirect_url' => $init->paymentUrl,
            'redirectUrl' => $init->paymentUrl,
            'payment_url' => $init->paymentUrl,
            'status' => $init->status,
            'code' => $init->code,
        ];
    }

    /**
     * Interroge l'état vérifié d'un paiement auprès de CinetPay.
     */
    public function getPayment(string $transactionId, ?string $countryCode = null): array
    {
        $countryEnum = $this->resolveCountry($countryCode);
        $client = $this->getClient($countryEnum);

        try {
            $status = $client->payments()->find($transactionId);

            $paymentStatus = match (true) {
                $status->isSuccessful() => 'completed',
                $status->isFinal() => 'failed',
                default => 'pending',
            };

            return [
                'transaction_id' => $status->transactionId,
                'merchant_transaction_id' => $status->merchantTransactionId,
                'status' => $status->status,
                'payment_status' => $paymentStatus,
                'is_successful' => $status->isSuccessful(),
                'is_final' => $status->isFinal(),
                'raw' => $status->raw,
            ];
        } catch (\Throwable $e) {
            Log::warning("[CinetPay] Échec consultation paiement {$transactionId}: " . $e->getMessage());
            return [
                'transaction_id' => $transactionId,
                'merchant_transaction_id' => $transactionId,
                'status' => 'PENDING',
                'payment_status' => 'pending',
                'is_successful' => false,
                'is_final' => false,
                'error' => $e->getMessage(),
                'raw' => [],
            ];
        }
    }

    /**
     * La réponse de l'API (relue avec l'identifiant STOCKÉ de l'enregistrement) concerne-t-elle bien cet enregistrement ?
     * Garde-fou contre toute confusion entre deux paiements : l'identifiant marchand ET, s'ils sont connus des deux côtés,
     * l'identifiant de transaction doivent correspondre.
     */
    public static function matchesRecord(object $record, array $state): bool
    {
        $reported = (string) ($state['merchant_transaction_id'] ?? '');
        $known = array_filter([
            (string) ($record->payment_intent_id ?? ''),
            substr((string) preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($record->reference ?? '')), 0, 30),
        ]);
        if ($reported === '' || !in_array($reported, $known, true)) {
            return false;
        }

        $reportedTx = (string) ($state['transaction_id'] ?? '');
        $storedTx = (string) ($record->payment_checkout_id ?? '');

        return $storedTx === '' || $reportedTx === '' || hash_equals($storedTx, $reportedTx);
    }

    /**
     * Le montant (ou la devise) rapporté par l'API est-il inférieur à ce qui est dû ? Si l'API ne fournit pas le montant,
     * seul le montant fixé à la création de la session fait foi. Un montant supérieur (frais inclus) n'est pas bloquant.
     */
    public static function isUnderpaid(int $expectedXof, array $state): bool
    {
        $raw = is_array($state['raw'] ?? null) ? $state['raw'] : [];
        $currency = strtoupper((string) ($raw['currency'] ?? ($raw['payment']['currency'] ?? '')));
        if ($currency !== '' && !in_array($currency, self::PAYABLE_CURRENCIES, true)) {
            return true;
        }
        $amount = $raw['amount'] ?? ($raw['payment']['amount'] ?? null);

        return is_numeric($amount) && (float) $amount + 0.5 < $expectedXof;
    }

    /**
     * Revérifie et synchronise le paiement d'une commande (utilisé par l'admin).
     */
    public function checkPaymentStatus(Order $order): bool
    {
        $targetId = $order->payment_intent_id ?: $order->payment_checkout_id ?: $order->reference ?: $order->id;
        $state = $this->getPayment($targetId, $order->customer_country);
        if (!empty($state['error'])) {
            return false;
        }

        app(OrderFulfillmentService::class)->settleGatewayPayment($order, $state, 'cinetpay');

        return !empty($state['is_successful']);
    }
}
