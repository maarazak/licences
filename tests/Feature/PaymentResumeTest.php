<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentResumeTest extends TestCase
{
    public function test_guest_is_redirected_to_login_when_resuming_payment(): void
    {
        $response = $this->get('/paiement/reprendre?commande=CMD-TEST1234');
        $response->assertRedirect();
        $this->assertStringContainsString('/connexion', $response->headers->get('Location'));
    }

    public function test_user_cannot_resume_another_users_order(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $order = Order::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'CMD-USER1',
            'user_id' => $user1->id,
            'product_name' => 'Test Product',
            'quantity' => 1,
            'unit_price_xof' => 2000,
            'total_xof' => 2000,
            'paid_with' => 'cinetpay',
            'status' => 'pending_payment',
            'payment_url' => 'https://checkout.cinetpay.com/pay/abc',
        ]);

        $response = $this->actingAs($user2)->get('/paiement/reprendre?commande=' . $order->id);
        $response->assertRedirect('/compte/achats');
        $response->assertSessionHas('error');
    }

    public function test_user_resumes_pending_payment_with_existing_payment_url(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'CMD-RESUME1',
            'user_id' => $user->id,
            'product_name' => 'Test Product',
            'quantity' => 1,
            'unit_price_xof' => 2000,
            'total_xof' => 2000,
            'paid_with' => 'cinetpay',
            'status' => 'pending_payment',
            'payment_url' => 'https://checkout.cinetpay.com/pay/valid-link',
        ]);

        $response = $this->actingAs($user)->get('/paiement/reprendre?commande=' . $order->id);
        // Should redirect to payment URL
        $this->assertTrue(
            $response->isRedirect() ||
            $response->headers->has('X-Inertia-Location')
        );
    }

    public function test_user_cannot_resume_already_paid_order(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'CMD-PAID1',
            'user_id' => $user->id,
            'product_name' => 'Test Product',
            'quantity' => 1,
            'unit_price_xof' => 2000,
            'total_xof' => 2000,
            'paid_with' => 'cinetpay',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/paiement/reprendre?commande=' . $order->id);
        $response->assertRedirect(route('account.orders.show', $order->id));
        $response->assertSessionHas('info');
    }
}
