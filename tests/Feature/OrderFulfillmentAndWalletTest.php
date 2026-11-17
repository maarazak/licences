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

    public function test_artisan_orders_maintain_command(): void
    {
        $this->artisan('orders:maintain')
            ->expectsOutputToContain('Démarrage de la maintenance des commandes...')
            ->expectsOutputToContain('Maintenance terminée avec succès.')
            ->assertExitCode(0);
    }
}
