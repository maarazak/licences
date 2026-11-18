<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CinetPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class CinetPayIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_order_and_redirects_to_cinetpay_url(): void
    {
        $user = User::factory()->create([
            'email' => 'abdoulrazakmaali@gmail.com',
            'first_name' => 'Abdoul Razak',
            'last_name' => 'Maali',
            'country' => 'BF',
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'supplier_id' => 'SUP-CP-TEST',
            'slug' => 'test-cinetpay-product-' . Str::random(5),
            'name' => 'Produit CinetPay Test',
            'category' => 'Bureautique',
            'cost_usd_cents' => 300,
            'price_xof_override' => 15000,
            'is_published' => true,
            'supplier_active' => true,
            'in_stock' => true,
        ]);

        $mockCinetPay = Mockery::mock(CinetPayService::class);
        $mockCinetPay->shouldReceive('createPaymentPage')
            ->once()
            ->withArgs(function ($args) {
                return $args['amount_xof'] === 15000
                    && $args['email'] === 'abdoulrazakmaali@gmail.com'
                    && !empty($args['reference']);
            })
            ->andReturn([
                'merchant_transaction_id' => 'CMD-TEST-1234',
                'transaction_id' => 'CP-TX-987654',
                'notify_token' => 'tok_secure_xyz',
                'payment_token' => 'ptok_abc',
                'redirect_url' => 'https://checkout.cinetpay.com/payment/987654',
                'redirectUrl' => 'https://checkout.cinetpay.com/payment/987654',
                'payment_url' => 'https://checkout.cinetpay.com/payment/987654',
                'status' => 'ACCEPTED',
                'code' => 201,
            ]);
        $this->app->instance(CinetPayService::class, $mockCinetPay);

        $this->actingAs($user, 'web');

        $response = $this->withHeaders(['X-Inertia' => 'true'])
            ->post(route('checkout.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
                'expectedTotalXof' => 15000,
                'country' => 'BF',
                'payWith' => 'cinetpay',
            ]);

        $response->assertStatus(409); // Inertia external redirect status

        $order = Order::where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals('cinetpay', $order->paid_with);
        $this->assertEquals('pending_payment', $order->status);
        $this->assertEquals('https://checkout.cinetpay.com/payment/987654', $order->payment_url);
        $this->assertEquals('CP-TX-987654', $order->payment_checkout_id);
        $this->assertEquals('CMD-TEST-1234', $order->payment_intent_id);
    }

    public function test_cinetpay_webhook_syncs_order_to_paid_and_delivers(): void
    {
        $user = User::factory()->create([
            'email' => 'client@example.com',
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'supplier_id' => 'SUP-CP-TEST-2',
            'slug' => 'test-cinetpay-product-2-' . Str::random(5),
            'name' => 'Produit CinetPay Test 2',
            'category' => 'Bureautique',
            'cost_usd_cents' => 300,
            'price_xof_override' => 20000,
            'is_published' => true,
            'supplier_active' => true,
            'in_stock' => true,
        ]);

        $order = Order::create([
            'id' => (string) Str::uuid(),
            'reference' => 'CMD-WEBHOOK-TEST',
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price_xof' => 20000,
            'total_xof' => 20000,
            'status' => 'pending_payment',
            'paid_with' => 'cinetpay',
            'payment_checkout_id' => 'CP-TX-WEBHOOK-1',
            'payment_intent_id' => 'CMD-WEBHOOK-TEST',
        ]);

        $mockCinetPay = Mockery::mock(CinetPayService::class);
        $mockCinetPay->shouldReceive('getPayment')
            ->with('CMD-WEBHOOK-TEST')
            ->once()
            ->andReturn([
                'transaction_id' => 'CP-TX-WEBHOOK-1',
                'merchant_transaction_id' => 'CMD-WEBHOOK-TEST',
                'status' => 'ACCEPTED',
                'payment_status' => 'completed',
                'is_successful' => true,
                'is_final' => true,
                'raw' => [],
            ]);
        $this->app->instance(CinetPayService::class, $mockCinetPay);

        $response = $this->postJson('/api/webhooks/cinetpay', [
            'merchant_transaction_id' => 'CMD-WEBHOOK-TEST',
            'transaction_id' => 'CP-TX-WEBHOOK-1',
            'notify_token' => 'tok_xyz',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'order_synced']);

        $fresh = $order->fresh();
        $this->assertTrue(in_array($fresh->status, ['paid', 'delivered'], true));
        $this->assertNotNull($fresh->paid_at);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'actor' => 'cinetpay',
            'type' => 'paid',
        ]);
    }

    public function test_seamless_checkout_returns_json_tokens_and_urls(): void
    {
        $user = User::factory()->create([
            'email' => 'seamless@example.com',
            'country' => 'BF',
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'supplier_id' => 'SUP-SEAMLESS',
            'slug' => 'test-seamless-product-' . Str::random(5),
            'name' => 'Produit Seamless',
            'category' => 'Bureautique',
            'cost_usd_cents' => 300,
            'price_xof_override' => 5000,
            'is_published' => true,
            'supplier_active' => true,
            'in_stock' => true,
        ]);

        $mockCinetPay = Mockery::mock(CinetPayService::class);
        $mockCinetPay->shouldReceive('createPaymentPage')
            ->once()
            ->andReturn([
                'merchant_transaction_id' => 'CMD-SEAMLESS-1',
                'transaction_id' => 'CP-TX-SEAMLESS',
                'notify_token' => 'tok_seamless',
                'payment_token' => 'ptok_seamless_xyz',
                'redirect_url' => 'https://checkout.cinetpay.com/payment/seamless',
                'payment_url' => 'https://checkout.cinetpay.com/payment/seamless',
                'status' => 'ACCEPTED',
                'code' => 201,
            ]);
        $this->app->instance(CinetPayService::class, $mockCinetPay);

        $this->actingAs($user, 'web');

        $response = $this->postJson(route('checkout.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'expectedTotalXof' => 5000,
            'country' => 'BF',
            'payWith' => 'cinetpay',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'seamless' => true,
            'payment_token' => 'ptok_seamless_xyz',
            'payment_url' => 'https://checkout.cinetpay.com/payment/seamless',
        ]);
    }

    public function test_return_page_redirects_paid_failed_orders_with_info(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'id' => (string) Str::uuid(),
            'reference' => 'CMD-PAID-FAIL',
            'user_id' => $user->id,
            'product_name' => 'Produit Test',
            'quantity' => 1,
            'unit_price_xof' => 2750,
            'total_xof' => 2750,
            'status' => 'failed',
            'failure_code' => 'insufficient_balance',
            'paid_at' => now(),
            'paid_with' => 'cinetpay',
        ]);

        $this->actingAs($user, 'web');

        $response = $this->get('/paiement/retour?commande=' . $order->id);
        $response->assertRedirect(route('account.orders.show', $order->id));
        $response->assertSessionHas('info');
    }
}
