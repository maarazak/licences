<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlowsellerException extends Exception
{
    public int $status;
    public string $codeStr;
    public int $retryAfterSeconds;

    public function __construct(int $status, string $codeStr, string $message, int $retryAfterSeconds = 0)
    {
        parent::__construct($message);
        $this->status = $status;
        $this->codeStr = $codeStr;
        $this->retryAfterSeconds = $retryAfterSeconds;
    }

    public function isDefinitive(): bool
    {
        $definitiveCodes = [
            'invalid_request',
            'invalid_quantity',
            'quantity_too_large',
            'emails_required',
            'invalid_email',
            'duplicate_email',
            'variation_not_supported',
            'emails_count_mismatch',
            'unauthorized',
            'insufficient_balance',
            'account_blocked',
            'product_not_found',
            'order_not_found',
            'product_inactive',
            'insufficient_stock',
            'preorder_not_supported',
            'delivery_failed',
            'shop_closed',
        ];

        return in_array($this->codeStr, $definitiveCodes, true);
    }
}

class FlowsellerService
{
    protected string $apiUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.flowseller.url', env('SUPPLIER_API_URL', 'https://flowseller-api.pe-supplykh.com')), '/');
        $this->apiKey = config('services.flowseller.key', env('SUPPLIER_API_KEY'));
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    protected function request(string $method, string $path, array $data = [], ?string $idempotencyKey = null, int $timeout = 30): array
    {
        if (!$this->isConfigured()) {
            throw new FlowsellerException(0, 'not_configured', 'SUPPLIER_API_KEY est absent de la configuration.');
        }

        $headers = [
            'Authorization' => "Bearer {$this->apiKey}",
            'Accept' => 'application/json',
        ];

        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        try {
            $client = Http::withHeaders($headers)->timeout($timeout);

            $response = match (strtoupper($method)) {
                'POST' => $client->post("{$this->apiUrl}{$path}", $data),
                default => $client->get("{$this->apiUrl}{$path}", $data),
            };

            if (!$response->successful()) {
                $payload = $response->json();
                $err = $payload['error'] ?? [];
                $code = $err['code'] ?? ('http_' . $response->status());
                $message = $err['message'] ?? ("Erreur fournisseur HTTP {$response->status()}");
                $retryAfter = (int) ($response->header('Retry-After') ?? 0);
                if ($retryAfter === 0 && preg_match('/Retry in (\d+)s/i', $message, $m)) {
                    $retryAfter = (int) $m[1];
                }

                if ($response->status() === 429) {
                    $cooldown = max(5, $retryAfter ?: 30);
                    \App\Models\Setting::set('flowseller_rate_limited_until', (string) (time() + $cooldown));

                    if ($retryAfter > 0 && $retryAfter <= 5) {
                        sleep($retryAfter);
                        return $this->request($method, $path, $data, $idempotencyKey, $timeout);
                    }
                }

                throw new FlowsellerException($response->status(), (string) $code, (string) $message, $retryAfter);
            }

            return $response->json() ?? [];
        } catch (FlowsellerException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error("[Flowseller] Network/Connection error: {$e->getMessage()}");
            throw new FlowsellerException(0, 'network_error', 'Connexion au fournisseur impossible : ' . $e->getMessage());
        }
    }

    /**
     * Récupère le solde du compte revendeur en USD.
     */
    public function getBalance(): array
    {
        $res = $this->request('GET', '/balance');
        return $res['data'] ?? $res;
    }

    /**
     * Récupère la liste de tous les produits chez le fournisseur.
     */
    public function listAllProducts(): array
    {
        $products = [];
        $seenIds = [];
        $invalid = 0;

        for ($page = 1; $page <= 50; $page++) {
            if ($page > 1) {
                usleep(150000); // Pause de 150ms pour respecter le débit de l'API
            }
            $payload = $this->request('GET', "/products?page={$page}&per_page=100");
            $data = $payload['data'] ?? [];

            if (!is_array($data)) {
                break;
            }

            foreach ($data as $item) {
                if (isset($item['id'])) {
                    $seenIds[] = (string) $item['id'];
                    $products[] = $item;
                } else {
                    $invalid++;
                }
            }

            $hasMore = $payload['pagination']['has_more'] ?? false;
            if (!$hasMore || empty($data)) {
                break;
            }
        }

        return [
            'products' => $products,
            'seenIds' => $seenIds,
            'invalid' => $invalid,
        ];
    }

    /**
     * Récupère les détails d'un produit par ID ou slug.
     */
    public function getProduct(string $idOrSlug): array
    {
        $res = $this->request('GET', '/products/' . urlencode($idOrSlug));
        return $res['data'] ?? $res;
    }

    /**
     * Achat automatique d'un produit chez le fournisseur avec clé d'idempotence.
     */
    public function createOrder(string $productId, int $quantity, ?array $emails = null, ?string $idempotencyKey = null): array
    {
        $body = [
            'product' => $productId,
            'quantity' => $quantity,
        ];

        if (!empty($emails)) {
            $body['emails'] = array_values(array_filter($emails));
        }

        $res = $this->request('POST', '/orders', $body, $idempotencyKey, 60);
        return $res['data'] ?? $res;
    }

    /**
     * Vérifie le statut d'une commande chez le fournisseur.
     */
    public function getOrder(string $orderId): array
    {
        $res = $this->request('GET', '/orders/' . urlencode($orderId));
        return $res['data'] ?? $res;
    }

    /**
     * Extrait les éléments de livraison (codes de licence, comptes, identifiants).
     */
    public static function extractDeliveryItems(array $order): array
    {
        $items = $order['items'] ?? [];
        $result = [];

        foreach ($items as $item) {
            if (is_string($item)) {
                $trimmed = trim($item);
                if ($trimmed !== '') {
                    $result[] = $trimmed;
                }
            } elseif (is_array($item)) {
                $result[] = json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return $result;
    }
}
