<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_sensitive_attributes_are_hidden_from_serialization(): void
    {
        $product = Product::create([
            'id' => (string) Str::uuid(),
            'supplier_id' => 'SUP-SECRET-999',
            'slug' => 'test-secret-product',
            'name' => 'Secret Product',
            'cost_usd_cents' => 125,
            'list_usd_cents' => 200,
            'is_published' => true,
        ]);

        $productArray = $product->toArray();
        $this->assertArrayNotHasKey('cost_usd_cents', $productArray);
        $this->assertArrayNotHasKey('list_usd_cents', $productArray);
        $this->assertArrayNotHasKey('supplier_id', $productArray);

        $order = Order::create([
            'id' => (string) Str::uuid(),
            'reference' => 'CMD-SEC-123',
            'user_id' => (string) Str::uuid(),
            'product_name' => 'Produit',
            'quantity' => 1,
            'unit_price_xof' => 2000,
            'total_xof' => 2000,
            'unit_cost_usd_cents' => 100,
            'admin_note' => 'Note confidentielle interne',
            'delivery_encrypted' => 'v2.enc.tag.data',
        ]);

        $orderArray = $order->toArray();
        $this->assertArrayNotHasKey('admin_note', $orderArray);
        $this->assertArrayNotHasKey('unit_cost_usd_cents', $orderArray);
        $this->assertArrayNotHasKey('delivery_encrypted', $orderArray);
    }

    public function test_admin_login_rate_limiting_blocks_brute_force(): void
    {
        RateLimiter::clear('admin-login:127.0.0.1:admin@licences.local');

        $admin = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'Sec',
            'email' => 'admin@licences.local',
            'password' => bcrypt('RealPassword123!'),
            'role' => 'admin',
        ]);

        // 5 bad password attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('admin.login'), [
                'email' => 'admin@licences.local',
                'password' => 'WrongPassword',
            ]);
            $response->assertSessionHasErrors('email');
        }

        // 6th attempt is throttled
        $response = $this->post(route('admin.login'), [
            'email' => 'admin@licences.local',
            'password' => 'RealPassword123!',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = session('errors')->get('email');
        $this->assertStringContainsString('Trop de tentatives', $errors[0]);
    }

    public function test_ticket_creation_does_not_link_foreign_order(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $foreignOrder = Order::create([
            'id' => (string) Str::uuid(),
            'reference' => 'CMD-VICTIM-99',
            'user_id' => $user2->id,
            'product_name' => 'Licence Windows',
            'quantity' => 1,
            'unit_price_xof' => 5000,
            'total_xof' => 5000,
            'status' => 'delivered',
        ]);

        $this->actingAs($user1, 'web');

        $response = $this->post(route('account.tickets.store'), [
            'subject' => 'Tentative IDOR ticket',
            'message' => 'Lien de commande étrangère',
            'order_id' => $foreignOrder->id,
        ]);
        $response->assertSessionHas('success');

        $ticket = Ticket::where('user_id', $user1->id)->latest()->first();
        $this->assertNotNull($ticket);
        // order_id must NOT be linked to user2's order
        $this->assertNull($ticket->order_id);
    }
}
