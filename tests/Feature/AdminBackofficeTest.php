<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CryptoService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminBackofficeTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.testsuite@example.com'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Admin TestSuite',
                'role' => 'admin',
                'is_blocked' => false,
            ]
        );
        $this->admin->role = 'admin';
        $this->admin->save();
    }

    public function test_admin_dashboard_renders_with_metrics(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get('/admin');
        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard');
        $response->assertViewHas('totalOrders');
        $response->assertViewHas('totalRevenueXof');
    }

    public function test_admin_can_adjust_client_balance_and_toggle_block(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'balance_xof' => 5000,
            'is_blocked' => false,
        ]);

        // Ajustement solde (+2500 FCFA)
        $response = $this->actingAs($this->admin, 'admin')
            ->post("/admin/clients/{$client->id}/adjust-balance", [
                'amount_xof' => 2500,
                'reason' => 'Geste commercial test',
            ]);

        $response->assertSessionHas('success');
        $this->assertEquals(7500, $client->fresh()->balance_xof);

        // Vérification de l'entrée dans le grand livre
        $this->assertDatabaseHas('wallet_entries', [
            'user_id' => $client->id,
            'amount_xof' => 2500,
            'balance_after_xof' => 7500,
        ]);

        // Vérification du journal d'audit
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'client.adjust_balance',
            'target' => $client->id,
        ]);

        // Blocage du compte client
        $blockResponse = $this->actingAs($this->admin, 'admin')
            ->post("/admin/clients/{$client->id}/toggle-block");

        $blockResponse->assertSessionHas('success');
        $this->assertTrue($client->fresh()->is_blocked);
    }

    public function test_admin_can_reveal_credentials_with_audit_trail(): void
    {
        $orderId = Str::uuid()->toString();
        $plain = "Compte: test@netflix.com\nMot de passe: Pass2026!";
        $encrypted = CryptoService::encrypt($plain, $orderId);

        $order = Order::create([
            'id' => $orderId,
            'reference' => 'CMD-AUDIT-' . strtoupper(Str::random(5)),
            'user_id' => $this->admin->id,
            'product_name' => 'Abonnement Test',
            'quantity' => 1,
            'unit_price_xof' => 3000,
            'total_xof' => 3000,
            'status' => 'delivered',
            'delivery_encrypted' => $encrypted,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get("/admin/commandes/{$order->id}/reveal");

        $response->assertStatus(200);
        $response->assertJson([
            'credentials' => $plain,
        ]);

        // Traçabilité de la consultation
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.reveal_credentials',
            'target' => $order->id,
        ]);
    }

    public function test_admin_can_update_shop_settings(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post('/admin/parametres', [
                'usdRate' => 675,
                'marginPercent' => 120,
                'minMarginXof' => 1500,
                'roundingStep' => 500,
                'autoPublish' => 1,
                'notice' => 'Offre spéciale du week-end',
            ]);

        $response->assertSessionHas('success');

        $this->assertEquals('675', Setting::get('usd_rate'));
        $this->assertEquals('120', Setting::get('default_margin_percent'));
        $this->assertEquals('1500', Setting::get('min_margin_xof'));
        $this->assertEquals('500', Setting::get('rounding_step'));
        $this->assertEquals('Offre spéciale du week-end', Setting::get('notice'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'settings.update',
        ]);
    }

    public function test_admin_can_deliver_order_manually(): void
    {
        Mail::fake();

        $order = Order::create([
            'id' => (string) Str::uuid(),
            'reference' => 'CMD-MANUAL-' . strtoupper(Str::random(6)),
            'user_id' => $this->admin->id,
            'product_name' => 'Canva Pro Ext',
            'quantity' => 1,
            'unit_price_xof' => 4500,
            'total_xof' => 4500,
            'status' => 'paid',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post("/admin/commandes/{$order->id}/deliver-manual", [
                'credentials' => "email: ext-supplier@gmail.com\npass: Secret123",
            ]);

        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals('delivered', $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertNotNull($order->delivery_encrypted);

        // Déchiffrement AES-256-GCM
        $decrypted = \App\Services\CryptoService::decrypt($order->delivery_encrypted, $order->id);
        $this->assertStringContainsString('ext-supplier@gmail.com', $decrypted);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.manual_delivery',
            'target' => $order->id,
        ]);
    }
}
