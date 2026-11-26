<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletEntry;
use App\Services\CryptoService;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderFulfillmentAndWalletTest extends TestCase
{
    public function test_crypto_service_encrypts_and_decrypts_accurately(): void
    {
        $crypto = app(CryptoService::class);
        $orderId = Str::uuid()->toString();
        $payload = json_encode(['W269N-WFGWX-YVC9B-4J6C9-T83GX', 'account: user@example.com / pass: Secret123!']);

        $encrypted = $crypto->encrypt($payload, $orderId);
        $this->assertStringStartsWith('v2.', $encrypted);

        $decrypted = $crypto->decrypt($encrypted, $orderId);
        $this->assertEquals($payload, $decrypted);

        // Wrong order ID fails authentication (AEAD integrity check returns null)
        $this->assertNull($crypto->decrypt($encrypted, Str::uuid()->toString()));
    }

    public function test_wallet_balance_order_payment_and_fulfillment(): void
    {
        $user = User::factory()->create([
            'balance_xof' => 20000,
        ]);

        $product = Product::where('is_published', true)->where('supplier_active', true)->first() ?? Product::create([
            'id' => Str::uuid()->toString(),
            'supplier_id' => 'TEST-PROD-1',
            'slug' => 'test-produit-' . Str::random(5),
            'name' => 'Produit Test',
            'category' => 'Bureautique',
            'cost_usd_cents' => 300,
            'price_xof_override' => 5000,
            'is_published' => true,
            'supplier_active' => true,
            'in_stock' => true,
        ]);

        $this->actingAs($user, 'web');

        $response = $this->post(route('checkout.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
            'email' => $user->email,
            'phone' => '+22670112233',
            'country' => 'BF',
            'paid_with' => 'balance',
        ]);

        $response->assertSessionHasNoErrors();

        $expectedTotal = $product->price_xof * 2;
        $expectedBalance = 20000 - $expectedTotal;

        // Order total deducted accurately
        $freshUser = User::find($user->id);
        $this->assertEquals($expectedBalance, $freshUser->balance_xof);

        $order = Order::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertContains($order->status, ['paid', 'processing', 'activation_awaiting', 'delivered', 'failed']);
        $this->assertEquals($expectedTotal, $order->total_xof);

        // Wallet entry recorded
        $walletEntry = WalletEntry::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($walletEntry);
        $this->assertEquals(-$expectedTotal, $walletEntry->amount_xof);
        $this->assertEquals($expectedBalance, $walletEntry->balance_after_xof);

        $response->assertRedirect(route('account.orders.show', $order->id));
    }

    public function test_wallet_balance_order_payment_with_inertia_request(): void
    {
        $user = User::factory()->create([
            'balance_xof' => 25000,
        ]);

        $product = Product::where('is_published', true)->where('supplier_active', true)->first() ?? Product::create([
            'id' => Str::uuid()->toString(),
            'supplier_id' => 'TEST-PROD-INERTIA',
            'slug' => 'test-produit-inertia-' . Str::random(5),
            'name' => 'Produit Test Inertia',
            'category' => 'Bureautique',
            'cost_usd_cents' => 300,
            'price_xof_override' => 5000,
            'is_published' => true,
            'supplier_active' => true,
            'in_stock' => true,
        ]);

        $this->actingAs($user, 'web');

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/commande/validation', [
            'productId' => $product->id,
            'quantity' => 1,
            'fullName' => 'Test User',
            'phone' => '+22670112233',
            'country' => 'BF',
            'payWith' => 'balance',
            'expectedTotalXof' => 5000,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);

        $order = Order::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertContains($order->status, ['paid', 'processing', 'activation_awaiting', 'delivered', 'failed']);
        $response->assertRedirect(route('account.orders.show', $order->id));
    }

    public function test_customer_can_choose_credit_on_failed_order(): void
    {
        $user = User::factory()->create([
            'balance_xof' => 1000,
            'role' => 'client',
            'is_blocked' => false,
        ]);

        $order = Order::create([
            'id' => Str::uuid()->toString(),
            'reference' => 'CMD-FAIL-' . strtoupper(Str::random(6)),
            'user_id' => $user->id,
            'product_name' => 'Produit Rupture',
            'quantity' => 1,
            'unit_price_xof' => 5000,
            'total_xof' => 5000,
            'status' => 'failed',
            'failure_code' => 'supplier_out_of_stock',
            'paid_with' => 'cinetpay',
            'created_at' => now(),
        ]);

        $this->actingAs($user, 'web');

        // Le client n'a pas le droit de déclencher l'avoir lui-même (réservé au back-office)
        $response = $this->post(route('account.orders.credit', $order->id));
        $response->assertStatus(403);

        // L'administrateur génère l'avoir depuis le back-office
        $admin = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin-test-' . Str::random(5) . '@licences.local',
            'password' => bcrypt('Secret123!'),
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'admin');
        $adminResponse = $this->post(route('admin.orders.refund_balance', $order->id));
        $adminResponse->assertSessionHas('success');

        // Solde augmenté de 5 000 FCFA
        $freshUser = User::find($user->id);
        $this->assertEquals(6000, $freshUser->balance_xof);

        // Commande passée à refunded
        $freshOrder = Order::find($order->id);
        $this->assertEquals('refunded', $freshOrder->status);
        $this->assertEquals('balance', $freshOrder->refund_method);
    }

    public function test_artisan_orders_maintain_command(): void
    {
        $this->artisan('orders:maintain')
            ->expectsOutputToContain('Démarrage de la maintenance des commandes...')
            ->expectsOutputToContain('Maintenance terminée avec succès.')
            ->assertExitCode(0);
    }

    public function test_client_wallet_deposit_creates_deposit_with_reference(): void
    {
        $user = User::factory()->create([
            'balance_xof' => 5000,
            'role' => 'client',
        ]);

        $mockCinetPay = \Mockery::mock(\App\Services\CinetPayService::class);
        $mockCinetPay->shouldReceive('createPaymentPage')
            ->once()
            ->andReturn([
                'transaction_id' => 'cp-trans-123',
                'merchant_transaction_id' => 'DEP-TEST-123',
                'redirect_url' => 'https://checkout.cinetpay.com/pay/123',
                'redirectUrl' => 'https://checkout.cinetpay.com/pay/123',
                'payment_url' => 'https://checkout.cinetpay.com/pay/123',
                'status' => 'ACCEPTED',
                'code' => 201,
            ]);
        $this->app->instance(\App\Services\CinetPayService::class, $mockCinetPay);

        $this->actingAs($user, 'web');

        $response = $this->withHeaders(['X-Inertia' => 'true'])
            ->post(route('account.wallet.deposit'), [
                'amount_xof' => 10000,
                'phone' => '+22507000000',
                'country' => 'CI',
                'fullName' => 'Kouassi Yao',
            ]);

        $response->assertStatus(409); // Inertia external redirect status

        $deposit = \App\Models\WalletDeposit::where('user_id', $user->id)->first();
        $this->assertNotNull($deposit);
        $this->assertStringStartsWith('DEP-', $deposit->reference);
        $this->assertEquals(10000, $deposit->amount_xof);
        $this->assertEquals('pending', $deposit->status);
        $this->assertEquals('cp-trans-123', $deposit->payment_checkout_id);
    }

    public function test_payment_return_syncs_and_credits_wallet_deposit(): void
    {
        $user = User::factory()->create([
            'balance_xof' => 2000,
            'role' => 'client',
        ]);

        $deposit = \App\Models\WalletDeposit::create([
            'id' => (string) Str::uuid(),
            'reference' => 'DEP-SYNC-' . strtoupper(Str::random(6)),
            'user_id' => $user->id,
            'amount_xof' => 8000,
            'status' => 'pending',
            'payment_checkout_id' => 'cp-checkout-' . Str::random(6),
        ]);

        $mockCinetPay = \Mockery::mock(\App\Services\CinetPayService::class);
        $mockCinetPay->shouldReceive('getPayment')
            ->once()
            ->andReturn([
                'merchant_transaction_id' => $deposit->reference,
                'transaction_id' => $deposit->payment_checkout_id,
                'status' => 'ACCEPTED',
                'is_successful' => true,
                'is_final' => true,
                'payment_status' => 'completed',
                'raw' => [],
            ]);
        $this->app->instance(\App\Services\CinetPayService::class, $mockCinetPay);

        $this->actingAs($user, 'web');

        $response = $this->get("/paiement/retour?depot={$deposit->id}");

        $response->assertRedirect(route('account.wallet.index'));

        $this->assertEquals(10000, $user->fresh()->balance_xof);
        $this->assertEquals('completed', $deposit->fresh()->status);
        $this->assertNotNull($deposit->fresh()->credited_at);

        $this->assertDatabaseHas('wallet_entries', [
            'user_id' => $user->id,
            'amount_xof' => 8000,
            'balance_after_xof' => 10000,
        ]);
    }
}
