<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Services\CinetPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Sécurité des paiements CinetPay : un webhook n'est qu'un signal ; l'état est relu avec l'identifiant STOCKÉ de la commande
 * ou de la recharge, les transitions sont atomiques, un paiement ne peut pas être rejoué sur autre chose.
 */
class CinetPaySecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $ref, array $extra = []): Order
    {
        $user = User::factory()->create();
        $product = Product::create([
            'id' => (string) Str::uuid(),
            'supplier_id' => 'SUP-' . Str::random(6),
            'slug' => 'p-' . Str::random(8),
            'name' => 'Produit test',
            'category' => 'Bureautique',
            'cost_usd_cents' => 300,
            'price_xof_override' => 20000,
            'is_published' => true,
            'supplier_active' => true,
            'in_stock' => true,
        ]);

        return Order::create(array_merge([
            'id' => (string) Str::uuid(),
            'reference' => $ref,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price_xof' => 20000,
            'total_xof' => 20000,
            'status' => 'pending_payment',
            'paid_with' => 'cinetpay',
            'payment_intent_id' => $ref,
            'payment_checkout_id' => 'TX-' . $ref,
        ], $extra));
    }

    private function state(string $ref, string $status, array $raw = []): array
    {
        $ok = $status === 'SUCCESS';

        return [
            'transaction_id' => 'TX-' . $ref,
            'merchant_transaction_id' => $ref,
            'status' => $status,
            'payment_status' => $ok ? 'completed' : ($status === 'FAILED' ? 'failed' : 'pending'),
            'is_successful' => $ok,
            'is_final' => $ok || $status === 'FAILED',
            'raw' => $raw,
        ];
    }

    private function mockGateway(): MockInterface
    {
        $mock = Mockery::mock(CinetPayService::class)->makePartial();
        $this->app->instance(CinetPayService::class, $mock);

        return $mock;
    }

    public function test_un_paiement_valide_ne_peut_pas_etre_rejoue_sur_une_autre_commande(): void
    {
        $cheap = $this->makeOrder('CMD-CHEAP');
        $victim = $this->makeOrder('CMD-VICTIM');

        $gateway = $this->mockGateway();
        // Seule la commande de la victime est relue, avec SON identifiant : elle est toujours en attente chez CinetPay.
        $gateway->shouldReceive('getPayment')->with('CMD-VICTIM', null)->andReturn($this->state('CMD-VICTIM', 'PENDING'));
        $gateway->shouldNotReceive('getPayment')->with('CMD-CHEAP', Mockery::any());

        // L'attaquant désigne la commande de la victime par son identifiant de transaction, avec un faux identifiant marchand.
        $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'FAKE-PAID', 'transaction_id' => $victim->payment_checkout_id])
            ->assertOk();

        $this->assertSame('pending_payment', $victim->fresh()->status);
        $this->assertSame('pending_payment', $cheap->fresh()->status);
    }

    public function test_l_identifiant_marchand_prime_et_la_commande_designee_est_relue_avec_son_propre_identifiant(): void
    {
        $paid = $this->makeOrder('CMD-REAL-PAID');
        $victim = $this->makeOrder('CMD-VICTIM-2');

        $gateway = $this->mockGateway();
        $gateway->shouldReceive('getPayment')->with('CMD-REAL-PAID', null)->once()->andReturn($this->state('CMD-REAL-PAID', 'SUCCESS'));

        $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'CMD-REAL-PAID', 'transaction_id' => $victim->payment_checkout_id])
            ->assertOk();

        $this->assertNotNull($paid->fresh()->paid_at);
        $this->assertSame('pending_payment', $victim->fresh()->status);
        $this->assertNull($victim->fresh()->paid_at);
    }

    public function test_reponse_de_l_api_qui_ne_correspond_pas_a_la_commande_est_ignoree(): void
    {
        $order = $this->makeOrder('CMD-MISMATCH');
        $gateway = $this->mockGateway();
        $gateway->shouldReceive('getPayment')->andReturn($this->state('AUTRE-COMMANDE', 'SUCCESS'));

        $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'CMD-MISMATCH'])->assertOk();

        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_jeton_de_notification_invalide_rejete_sans_aucun_appel_ni_changement(): void
    {
        $order = $this->makeOrder('CMD-TOKEN', ['notify_token' => 'jeton-secret-de-la-commande']);
        $gateway = $this->mockGateway();
        $gateway->shouldNotReceive('getPayment');

        $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'CMD-TOKEN', 'notify_token' => 'mauvais'])->assertStatus(400);
        $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'CMD-TOKEN'])->assertStatus(400);

        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_bon_jeton_de_notification_accepte(): void
    {
        $order = $this->makeOrder('CMD-TOKEN-OK', ['notify_token' => 'jeton-secret-ok']);
        $gateway = $this->mockGateway();
        $gateway->shouldReceive('getPayment')->with('CMD-TOKEN-OK', null)->once()->andReturn($this->state('CMD-TOKEN-OK', 'SUCCESS'));

        $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'CMD-TOKEN-OK', 'notify_token' => 'jeton-secret-ok'])->assertOk();

        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_le_jeton_de_notification_est_enregistre_et_jamais_serialise(): void
    {
        $order = $this->makeOrder('CMD-HIDDEN', ['notify_token' => 'tok-prive']);

        $this->assertSame('tok-prive', $order->fresh()->notify_token);
        $this->assertArrayNotHasKey('notify_token', $order->fresh()->toArray());
    }

    public function test_notification_rejouee_trois_fois_un_seul_evenement_paye(): void
    {
        $order = $this->makeOrder('CMD-REPLAY');
        $gateway = $this->mockGateway();
        $gateway->shouldReceive('getPayment')->andReturn($this->state('CMD-REPLAY', 'SUCCESS'));

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'CMD-REPLAY'])->assertOk();
        }

        $this->assertSame(1, \App\Models\OrderEvent::where('order_id', $order->id)->where('type', 'paid')->count());
    }

    public function test_paiement_tardif_sur_commande_expiree_est_repris(): void
    {
        $order = $this->makeOrder('CMD-LATE', ['status' => 'expired']);
        $gateway = $this->mockGateway();
        $gateway->shouldReceive('getPayment')->andReturn($this->state('CMD-LATE', 'SUCCESS'));

        $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'CMD-LATE'])->assertOk();

        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertNotSame('expired', $order->fresh()->status);
    }

    public function test_montant_inferieur_au_du_ne_livre_pas_et_passe_en_echec_a_traiter(): void
    {
        $order = $this->makeOrder('CMD-UNDER');
        $gateway = $this->mockGateway();
        $gateway->shouldReceive('getPayment')->andReturn($this->state('CMD-UNDER', 'SUCCESS', ['amount' => 500, 'currency' => 'XOF']));

        $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'CMD-UNDER'])->assertOk();

        $fresh = $order->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertSame('amount_mismatch', $fresh->failure_code);
        $this->assertNull($fresh->delivery_encrypted);
    }

    public function test_devise_non_fcfa_rapportee_est_traitee_comme_sous_paiement(): void
    {
        $order = $this->makeOrder('CMD-GNF');
        $gateway = $this->mockGateway();
        $gateway->shouldReceive('getPayment')->andReturn($this->state('CMD-GNF', 'SUCCESS', ['amount' => 20000, 'currency' => 'GNF']));

        $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'CMD-GNF'])->assertOk();

        $this->assertSame('amount_mismatch', $order->fresh()->failure_code);
    }

    public function test_relecture_impossible_demande_a_cinetpay_de_reessayer(): void
    {
        $order = $this->makeOrder('CMD-RETRY');
        $gateway = $this->mockGateway();
        $gateway->shouldReceive('getPayment')->andReturn(['status' => 'PENDING', 'payment_status' => 'pending', 'is_successful' => false, 'is_final' => false, 'error' => 'timeout', 'raw' => []]);

        $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'CMD-RETRY'])->assertStatus(503);
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_notification_inconnue_ou_vide(): void
    {
        $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'INCONNUE'])->assertStatus(404);
        $this->postJson('/api/webhooks/cinetpay', [])->assertStatus(400);
    }

    public function test_recharge_creditee_une_seule_fois_malgre_les_notifications_rejouees(): void
    {
        $user = User::factory()->create(['balance_xof' => 1000]);
        $deposit = WalletDeposit::create([
            'id' => (string) Str::uuid(), 'reference' => 'DEP-ONCE', 'user_id' => $user->id, 'amount_xof' => 5000,
            'status' => 'pending', 'payment_intent_id' => 'DEP-ONCE', 'payment_checkout_id' => 'TX-DEP-ONCE',
        ]);
        $gateway = $this->mockGateway();
        $gateway->shouldReceive('getPayment')->andReturn($this->state('DEP-ONCE', 'SUCCESS'));

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'DEP-ONCE'])->assertOk();
        }

        $this->assertSame(6000, $user->fresh()->balance_xof);
        $this->assertSame(1, \App\Models\WalletEntry::where('reference_id', $deposit->id)->count());
    }

    public function test_un_paiement_valide_ne_credite_pas_une_autre_recharge(): void
    {
        $user = User::factory()->create(['balance_xof' => 0]);
        $small = WalletDeposit::create(['id' => (string) Str::uuid(), 'reference' => 'DEP-SMALL', 'user_id' => $user->id, 'amount_xof' => 500, 'status' => 'pending', 'payment_intent_id' => 'DEP-SMALL', 'payment_checkout_id' => 'TX-DEP-SMALL']);
        $big = WalletDeposit::create(['id' => (string) Str::uuid(), 'reference' => 'DEP-BIG', 'user_id' => $user->id, 'amount_xof' => 500000, 'status' => 'pending', 'payment_intent_id' => 'DEP-BIG', 'payment_checkout_id' => 'TX-DEP-BIG']);
        $gateway = $this->mockGateway();
        // Seule la grosse recharge (celle que la requête désigne) est relue, avec SON identifiant stocké.
        $gateway->shouldReceive('getPayment')->with('DEP-BIG', Mockery::any())->once()->andReturn($this->state('DEP-BIG', 'PENDING'));
        $gateway->shouldNotReceive('getPayment')->with('DEP-SMALL', Mockery::any());

        // Paiement réel de la petite recharge, mais notification qui désigne la grosse par son identifiant de transaction.
        $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'FAUX', 'transaction_id' => 'TX-DEP-BIG'])->assertOk();

        $this->assertSame(0, $user->fresh()->balance_xof);
        $this->assertSame('pending', $big->fresh()->status);
        $this->assertSame('pending', $small->fresh()->status);
    }

    public function test_recharge_en_sous_paiement_ne_credite_rien(): void
    {
        $user = User::factory()->create(['balance_xof' => 0]);
        $deposit = WalletDeposit::create(['id' => (string) Str::uuid(), 'reference' => 'DEP-UNDER', 'user_id' => $user->id, 'amount_xof' => 50000, 'status' => 'pending', 'payment_intent_id' => 'DEP-UNDER', 'payment_checkout_id' => 'TX-DEP-UNDER']);
        $gateway = $this->mockGateway();
        $gateway->shouldReceive('getPayment')->andReturn($this->state('DEP-UNDER', 'SUCCESS', ['amount' => 100, 'currency' => 'XOF']));

        $this->postJson('/api/webhooks/cinetpay', ['merchant_transaction_id' => 'DEP-UNDER'])->assertOk();

        $this->assertSame(0, $user->fresh()->balance_xof);
        $this->assertSame('failed', $deposit->fresh()->status);
    }

    public function test_pays_a_devise_non_fcfa_refuse_avant_tout_appel_reseau(): void
    {
        $service = new CinetPayService();
        foreach (['GN', 'CD'] as $country) {
            try {
                $service->createPaymentPage(['reference' => 'CMD-X', 'amount_xof' => 15000, 'country' => $country, 'return_url' => 'https://exemple.test/retour']);
                $this->fail("Le pays {$country} aurait dû être refusé.");
            } catch (\Exception $e) {
                $this->assertStringContainsString('pas pris en charge', $e->getMessage());
            }
        }
    }

    public function test_correspondance_d_identite_et_sous_paiement(): void
    {
        $order = new Order(['reference' => 'CMD-ID', 'payment_intent_id' => 'CMD-ID', 'payment_checkout_id' => 'TX-1']);
        $this->assertTrue(CinetPayService::matchesRecord($order, ['merchant_transaction_id' => 'CMD-ID', 'transaction_id' => 'TX-1']));
        $this->assertTrue(CinetPayService::matchesRecord($order, ['merchant_transaction_id' => 'CMD-ID']));
        $this->assertFalse(CinetPayService::matchesRecord($order, ['merchant_transaction_id' => 'AUTRE']));
        $this->assertFalse(CinetPayService::matchesRecord($order, ['merchant_transaction_id' => 'CMD-ID', 'transaction_id' => 'TX-2']));
        $this->assertFalse(CinetPayService::matchesRecord($order, []));

        $this->assertFalse(CinetPayService::isUnderpaid(1000, ['raw' => []]), 'sans montant rapporté, le montant fixé à la création fait foi');
        $this->assertFalse(CinetPayService::isUnderpaid(1000, ['raw' => ['amount' => 1000, 'currency' => 'XOF']]));
        $this->assertFalse(CinetPayService::isUnderpaid(1000, ['raw' => ['amount' => 1030, 'currency' => 'XOF']]), 'frais inclus : pas bloquant');
        $this->assertTrue(CinetPayService::isUnderpaid(1000, ['raw' => ['amount' => 999, 'currency' => 'XOF']]));
        $this->assertTrue(CinetPayService::isUnderpaid(1000, ['raw' => ['amount' => 5000, 'currency' => 'CDF']]));
    }
}
