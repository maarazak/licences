<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PawaPayService
{
    public const MOBILE_MONEY_CONFIG = [
        'BFA' => ['currency' => 'XOF', 'name' => 'Burkina Faso'],
        'CIV' => ['currency' => 'XOF', 'name' => "Côte d'Ivoire"],
        'SEN' => ['currency' => 'XOF', 'name' => 'Sénégal'],
        'BEN' => ['currency' => 'XOF', 'name' => 'Bénin'],
        'CMR' => ['currency' => 'XAF', 'name' => 'Cameroun'],
        'COG' => ['currency' => 'XAF', 'name' => 'Congo'],
        'GAB' => ['currency' => 'XAF', 'name' => 'Gabon'],
    ];

    public const ISO2_TO_ISO3 = [
        'BF' => 'BFA',
        'CI' => 'CIV',
        'SN' => 'SEN',
        'BJ' => 'BEN',
        'CM' => 'CMR',
        'CG' => 'COG',
        'GA' => 'GAB',
        'ML' => 'MLI',
        'NE' => 'NER',
        'TG' => 'TGO',
        'CD' => 'COD',
        'GH' => 'GHA',
        'NG' => 'NGA',
    ];

    public const COUNTRY_DIALING_CODES = [
        'BF' => '226', 'BFA' => '226',
        'CI' => '225', 'CIV' => '225',
        'SN' => '221', 'SEN' => '221',
        'BJ' => '229', 'BEN' => '229',
        'CM' => '237', 'CMR' => '237',
        'CG' => '242', 'COG' => '242',
        'GA' => '241', 'GAB' => '241',
    ];

    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('PAWAPAY_API_URL', 'https://api.sandbox.pawapay.io/v2'), '/');
        $this->token = env('PAWAPAY_API_TOKEN', '');
    }

    public static function toIso3Country(?string $code): ?string
    {
        if (!$code) return null;
        $clean = strtoupper(trim($code));
        if (strlen($clean) === 3) return $clean;
        return self::ISO2_TO_ISO3[$clean] ?? null;
    }

    public static function formatPhone(?string $phone, ?string $countryCode): ?string
    {
        if (!$phone) return null;
        $digits = preg_replace('/[^0-9]/', '', preg_replace('/^00/', '', $phone));
        if (!$digits) return null;

        $countryKey = strtoupper(trim($countryCode ?? ''));
        $dialing = self::COUNTRY_DIALING_CODES[$countryKey] ?? ($countryKey === '' ? '226' : null);

        if ($dialing && !str_starts_with($digits, $dialing) && strlen($digits) <= 10) {
            $digits = $dialing . $digits;
        }

        return (strlen($digits) >= 8 && strlen($digits) <= 15) ? $digits : null;
    }

    public static function sanitizeCustomerMessage(string $reference): string
    {
        $clean = trim(preg_replace('/[^A-Za-z0-9 ]/', '', $reference));
        return substr(strlen($clean) >= 4 ? $clean : str_pad("CMD {$clean}", 4, 'X'), 0, 22);
    }

    public static function sanitizeReason(?string $description): ?string
    {
        if (!$description) return null;
        $clean = trim(preg_replace('/[\r\n\t]+/', ' ', $description));
        return $clean ? substr($clean, 0, 50) : null;
    }

    public static function sanitizeReturnUrl(string $url): string
    {
        $parts = parse_url($url);
        if (($parts['host'] ?? '') === 'localhost') {
            $url = str_replace('//localhost', '//127.0.0.1', $url);
        }
        return $url;
    }

    public static function isPaymentUrlValid(?string $url): bool
    {
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) return false;
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        if (!isset($query['Expires'])) return true;
        $expires = (int) $query['Expires'];
        return ($expires - time()) > 60;
    }

    /**
     * Initialise une session Hosted Payment Page pawaPay v2 avec fallback résilient.
     */
    public function createPaymentPage(array $input): array
    {
        $iso3 = self::toIso3Country($input['country'] ?? null);
        $config = ($iso3 && isset(self::MOBILE_MONEY_CONFIG[$iso3])) ? self::MOBILE_MONEY_CONFIG[$iso3] : self::MOBILE_MONEY_CONFIG['BFA'];
        $preferredCountry = ($iso3 && isset(self::MOBILE_MONEY_CONFIG[$iso3])) ? $iso3 : 'BFA';
        $currency = $config['currency'];

        $phone = self::formatPhone($input['phone'] ?? null, $preferredCountry);
        $customerMessage = self::sanitizeCustomerMessage($input['reference']);
        $returnUrl = self::sanitizeReturnUrl($input['return_url']);
        $reason = self::sanitizeReason($input['description'] ?? null);

        $requestSession = function (string $country, string $sessionCurrency, ?string $phoneNumber, int $attempt) use ($input, $returnUrl, $customerMessage, $reason) {
            $body = [
                'depositId' => $input['deposit_id'],
                'returnUrl' => $returnUrl,
                'customerMessage' => $customerMessage,
                'amountDetails' => [
                    'amount' => (string) $input['amount_xof'],
                    'currency' => $sessionCurrency,
                ],
                'language' => ($input['lang'] ?? 'fr') === 'en' ? 'EN' : 'FR',
                'country' => $country,
                'metadata' => [
                    ['orderId' => $input['order_id'] ?? $input['deposit_id']],
                    ['reference' => $input['reference']],
                    ['customerEmail' => $input['email']],
                ],
            ];

            if ($reason) $body['reason'] = $reason;
            if ($phoneNumber) $body['phoneNumber'] = $phoneNumber;

            $response = Http::withToken($this->token)
                ->withHeaders([
                    'x-idempotency-key' => "paymentpage-{$input['deposit_id']}-{$attempt}",
                    'accept' => 'application/json',
                ])
                ->timeout(25)
                ->post("{$this->baseUrl}/paymentpage", $body);

            $data = $response->json();

            if (isset($data['failureReason']['failureMessage'])) {
                throw new Exception($data['failureReason']['failureMessage']);
            }

            if (!$response->successful() || empty($data['redirectUrl'])) {
                throw new Exception($data['message'] ?? 'Échec de création pawaPay (' . $response->status() . ')');
            }

            return [
                'deposit_id' => $data['depositId'] ?? $input['deposit_id'],
                'status' => $data['status'] ?? 'ACCEPTED',
                'redirect_url' => $data['redirectUrl'],
            ];
        };

        // 1ère tentative avec pays, devise et numéro
        try {
            return $requestSession($preferredCountry, $currency, $phone, 1);
        } catch (Exception $e1) {
            Log::warning("[pawaPay] tentative 1 échouée ({$e1->getMessage()}), essai sans téléphone");

            // 2ème tentative sans numéro pour laisser le client choisir sur la page hébergée
            if ($phone) {
                try {
                    return $requestSession($preferredCountry, $currency, null, 2);
                } catch (Exception $e2) {
                    if ($preferredCountry !== 'BFA') {
                        return $requestSession('BFA', 'XOF', null, 3);
                    }
                    throw $e2;
                }
            }

            if ($preferredCountry !== 'BFA') {
                return $requestSession('BFA', 'XOF', null, 3);
            }

            throw $e1;
        }
    }

    /**
     * Relit l'état authentique d'un paiement (deposit) auprès de pawaPay.
     */
    public function getDeposit(string $depositId): array
    {
        $response = Http::withToken($this->token)
            ->timeout(25)
            ->get("{$this->baseUrl}/deposits/" . urlencode($depositId));

        if (!$response->successful()) {
            throw new Exception("Impossible de relire le deposit pawaPay {$depositId} (" . $response->status() . ")");
        }

        $payload = $response->json();
        $item = is_array($payload) && isset($payload[0]) ? $payload[0] : (is_array($payload) && isset($payload['data']) ? $payload['data'] : $payload);

        $status = strtoupper($item['status'] ?? 'PENDING');
        $paymentStatus = match ($status) {
            'COMPLETED' => 'completed',
            'FAILED' => 'failed',
            'EXPIRED' => 'expired',
            default => 'pending',
        };

        return [
            'deposit_id' => $item['depositId'] ?? $depositId,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'amount' => isset($item['amount']) ? (int) $item['amount'] : null,
            'currency' => strtoupper($item['currency'] ?? 'XOF'),
            'country' => $item['country'] ?? '',
            'provider_transaction_id' => $item['providerTransactionId'] ?? '',
            'failure_code' => $item['failureReason']['failureCode'] ?? null,
            'failure_message' => $item['failureReason']['failureMessage'] ?? null,
        ];
    }
}
