<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletEntry;
use App\Services\CryptomusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/** Webhook Cryptomus (signé) : transitions atomiques, doublons sans effet, paiement tardif repris, crédit unique. */
class CryptomusSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function signed(bool $valid = true): void
    {
        $mock = Mockery::mock(CryptomusService::class)->makePartial();
        $mock->shouldReceive('verifyWebhookSignature')->andReturn($valid);
        $this->app->instance(CryptomusService::class, $mock);
    }

    private function makeOrder(string $ref, array $extra = []): Order
    {
        $user = User::factory()->create();
        $product = Product::create([
            'id' => (string) Str::uuid(), 'supplier_id' => 'SUP-' . Str::random(6), 'slug' => 'p-' . Str::random(8),
            'name' => 'Produit test', 'category' => 'Bureautique', 'cost_usd_cents' => 300, 'price_xof_override' => 20000,
            'is_published' => true, 'supplier_active' => true, 'in_stock' => true,
        ]);

        return Order::create(array_merge([
            'id' => (string) Str::uuid(), 'reference' => $ref, 'user_id' => $user->id, 'product_id' => $product->id,
            'product_name' => $product->name, 'quantity' => 1, 'unit_price_xof' => 20000, 'total_xof' => 20000,
            'status' => 'pending_payment', 'paid_with' => 'cryptomus', 'payment_checkout_id' => 'uuid-' . $ref,
        ], $extra));
    }

    private function hook(string $ref, string $status, array $extra = [])
    {
        return $this->postJson('/api/webhooks/cryptomus', array_merge(['order_id' => $ref, 'uuid' => 'uuid-' . $ref, 'status' => $status], $extra));
    }

    public function test_signature_invalide_rejetee_sans_changement(): void
    {
        $order = $this->makeOrder('CRY-SIG');
        $this->signed(false);

        $this->hook('CRY-SIG', 'paid')->assertStatus(400);

        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_notification_rejouee_un_seul_evenement_paye(): void
    {
        $order = $this->makeOrder('CRY-REPLAY');
        $this->signed();

        for ($i = 0; $i < 3; $i++) {
            $this->hook('CRY-REPLAY', 'paid')->assertOk();
        }

        $this->assertSame(1, OrderEvent::where('order_id', $order->id)->where('type', 'paid')->count());
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_paiement_tardif_sur_commande_expiree_est_repris(): void
    {
        $order = $this->makeOrder('CRY-LATE', ['status' => 'expired']);
        $this->signed();

        $this->hook('CRY-LATE', 'paid_over')->assertOk();

        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertNotSame('expired', $order->fresh()->status);
    }

    public function test_echec_ne_defait_pas_une_commande_deja_payee(): void
    {
        $order = $this->makeOrder('CRY-ORDER');
        $this->signed();
        $this->hook('CRY-ORDER', 'paid')->assertOk();
        $this->hook('CRY-ORDER', 'cancel')->assertOk();

        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertNotSame('payment_failed', $order->fresh()->status);
    }

    public function test_echec_marque_la_commande_en_attente_en_echec_de_paiement(): void
    {
        $order = $this->makeOrder('CRY-FAIL');
        $this->signed();

        $this->hook('CRY-FAIL', 'fail')->assertOk();

        $this->assertSame('payment_failed', $order->fresh()->status);
    }

    public function test_statut_intermediaire_ne_change_rien(): void
    {
        $order = $this->makeOrder('CRY-WAIT');
        $this->signed();

        $this->hook('CRY-WAIT', 'check')->assertOk();
        $this->hook('CRY-WAIT', 'wrong_amount')->assertOk();

        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_recharge_creditee_une_seule_fois(): void
    {
        $user = User::factory()->create(['balance_xof' => 1000]);
        $deposit = WalletDeposit::create([
            'id' => (string) Str::uuid(), 'reference' => 'DEP-CRY', 'user_id' => $user->id, 'amount_xof' => 5000,
            'status' => 'pending', 'payment_checkout_id' => 'uuid-DEP-CRY',
        ]);
        $this->signed();

        for ($i = 0; $i < 3; $i++) {
            $this->hook('DEP-CRY', 'paid')->assertOk();
        }

        $this->assertSame(6000, $user->fresh()->balance_xof);
        $this->assertSame(1, WalletEntry::where('reference_id', $deposit->id)->count());
        $this->assertSame('completed', $deposit->fresh()->status);
    }

    public function test_recharge_echouee_puis_notification_payee_ne_credite_pas(): void
    {
        $user = User::factory()->create(['balance_xof' => 0]);
        $deposit = WalletDeposit::create([
            'id' => (string) Str::uuid(), 'reference' => 'DEP-CRY-F', 'user_id' => $user->id, 'amount_xof' => 5000,
            'status' => 'pending', 'payment_checkout_id' => 'uuid-DEP-CRY-F',
        ]);
        $this->signed();

        $this->hook('DEP-CRY-F', 'cancel')->assertOk();
        $this->hook('DEP-CRY-F', 'paid')->assertOk();

        $this->assertSame(0, $user->fresh()->balance_xof);
        $this->assertSame('failed', $deposit->fresh()->status);
    }

    public function test_identifiant_de_commande_different_de_l_enregistrement_est_ignore(): void
    {
        $a = $this->makeOrder('CRY-A');
        $this->signed();

        // L'uuid désigne la commande A, mais l'order_id rapporté est celui d'une autre : on n'applique rien à A.
        $this->postJson('/api/webhooks/cryptomus', ['order_id' => 'CRY-AUTRE', 'uuid' => 'uuid-CRY-A', 'status' => 'paid'])->assertOk();

        $this->assertSame('pending_payment', $a->fresh()->status);
    }
}
