<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Services\CryptomusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CryptomusPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cryptomus.merchant_id' => 'test-merchant-uuid']);
        config(['services.cryptomus.payment_key' => 'test-secret-key-12345']);
        Setting::set('usdRate', '669');
    }

    public function test_cryptomus_service_converts_xof_to_usd_properly(): void
    {
        $service = new CryptomusService();
        $usd = $service->convertXofToUsd(6690);
        $this->assertEquals('10.00', $usd);

        $usdSmall = $service->convertXofToUsd(2750);
        $this->assertEquals('4.11', $usdSmall);
    }

    public function test_cryptomus_webhook_rejects_invalid_signature(): void
    {
        $response = $this->postJson('/api/webhooks/cryptomus', [
            'order_id' => 'CMD-TEST-1',
            'status' => 'paid',
            'sign' => 'bad-signature',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid signature']);
    }

    public function test_cryptomus_webhook_marks_order_as_paid_with_valid_signature(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'supplier_id' => 'supp-test-1',
            'slug' => 'produit-test-' . uniqid(),
            'name' => 'Produit Test',
            'cost_usd_cents' => 300,
            'price_xof_override' => 5000,
            'is_published' => true,
            'supplier_active' => true,
            'in_stock' => true,
            'supplier_purchasable' => true,
        ]);

        $order = Order::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'CMD-CRYPTO-101',
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price_xof' => 5000,
            'total_xof' => 5000,
            'paid_with' => 'cryptomus',
            'status' => 'pending_payment',
            'customer_email' => $user->email,
        ]);

        $payload = [
            'type' => 'payment',
            'uuid' => 'cryptomus-invoice-uuid-1',
            'order_id' => $order->reference,
            'amount' => '7.47',
            'payment_amount' => '7.47',
            'payment_amount_usd' => '7.47',
            'merchant_amount' => '7.47',
            'commission' => '0.00',
            'is_final' => true,
            'status' => 'paid',
            'from' => '0x123...',
            'wallet_address_uuid' => null,
            'network' => 'tron',
            'currency' => 'USDT',
            'payer_currency' => 'USDT',
            'additional_data' => null,
        ];

        // Calcul signature Cryptomus : md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)) . $paymentKey)
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $sign = md5(base64_encode($json) . 'test-secret-key-12345');
        $payload['sign'] = $sign;

        $response = $this->postJson('/api/webhooks/cryptomus', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'order_synced']);

        $refreshed = $order->fresh();
        $this->assertContains($refreshed->status, ['paid', 'delivered']);
        $this->assertEquals('cryptomus', $refreshed->paid_with);
        $this->assertNotNull($refreshed->paid_at);
        $this->assertEquals('cryptomus-invoice-uuid-1', $refreshed->payment_checkout_id);
    }

    public function test_cryptomus_webhook_credits_wallet_deposit_properly(): void
    {
        $user = User::factory()->create([
            'balance_xof' => 1000,
        ]);

        $deposit = WalletDeposit::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'DEP-CRYPTO-202',
            'user_id' => $user->id,
            'amount_xof' => 15000,
            'customer_country' => 'CI',
            'status' => 'pending',
        ]);

        $payload = [
            'type' => 'payment',
            'uuid' => 'cryptomus-dep-uuid-2',
            'order_id' => $deposit->reference,
            'amount' => '22.42',
            'payment_amount' => '22.42',
            'status' => 'paid',
            'network' => 'bep20',
            'currency' => 'USDT',
            'payer_currency' => 'USDT',
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $payload['sign'] = md5(base64_encode($json) . 'test-secret-key-12345');

        $response = $this->postJson('/api/webhooks/cryptomus', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'deposit_synced']);

        $depositRefreshed = $deposit->fresh();
        $this->assertEquals('completed', $depositRefreshed->status);
        $this->assertNotNull($depositRefreshed->paid_at);
        $this->assertNotNull($depositRefreshed->credited_at);

        $userRefreshed = $user->fresh();
        $this->assertEquals(16000, $userRefreshed->balance_xof); // 1000 initial + 15000
    }
}
