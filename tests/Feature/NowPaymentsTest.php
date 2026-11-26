<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletEntry;
use App\Services\NowPaymentsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/** NOWPayments : facture, signature IPN, relecture chez NOWPayments, transitions atomiques, crédit unique. */
class NowPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.nowpayments.api_key' => 'cle-api-test',
            'services.nowpayments.ipn_secret' => 'secret-ipn-test',
            'services.nowpayments.base_url' => 'https://api.nowpayments.io/v1',
        ]);
        Setting::set('usdRate', 669);
    }

    /** Service partiel : signature acceptée, relecture simulée. */
    private function service(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(NowPaymentsService::class)->makePartial();
        $mock->shouldReceive('verifyWebhookSignature')->andReturn(true);
        $this->app->instance(NowPaymentsService::class, $mock);

        return $mock;
    }

    private function verified(string $ref, string $status, array $extra = []): array
    {
        return array_merge([
            'payment_id' => '5001', 'order_id' => $ref, 'payment_status' => $status,
            'price_amount' => 29.9, 'price_currency' => 'usd', 'pay_currency' => 'USDTTRC20', 'network' => 'trx',
        ], $extra);
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
            'status' => 'pending_payment', 'paid_with' => 'nowpayments', 'payment_checkout_id' => 'inv:900', 'payment_intent_id' => $ref,
        ], $extra));
    }

    private function makeDeposit(string $ref, int $amount = 5000, array $extra = []): WalletDeposit
    {
        $user = User::factory()->create(['balance_xof' => 1000]);

        return WalletDeposit::create(array_merge([
            'id' => (string) Str::uuid(), 'reference' => $ref, 'user_id' => $user->id, 'amount_xof' => $amount,
            'status' => 'pending', 'gateway' => 'nowpayments', 'payment_checkout_id' => 'inv:901', 'payment_intent_id' => $ref,
        ], $extra));
    }

    private function ipn(string $ref, array $extra = [])
    {
        return $this->postJson('/api/webhooks/nowpayments', array_merge(['order_id' => $ref, 'payment_id' => 5001, 'payment_status' => 'finished'], $extra));
    }

    // ─── Service ──────────────────────────────────────────────────────────────────

    public function test_conversion_fcfa_vers_dollar(): void
    {
        $service = new NowPaymentsService();
        $this->assertSame(29.9, $service->convertXofToUsd(20000));
        $this->assertSame(0.5, $service->convertXofToUsd(100), 'minimum 0,50 $');
    }

    public function test_signature_ipn_avec_cles_triees_ou_corps_deja_trie(): void
    {
        $service = new NowPaymentsService();
        $payload = ['payment_status' => 'finished', 'order_id' => 'EVP-1', 'payment_id' => 5001, 'nested' => ['b' => 1, 'a' => 2]];

        $sorted = ['nested' => ['a' => 2, 'b' => 1], 'order_id' => 'EVP-1', 'payment_id' => 5001, 'payment_status' => 'finished'];
        $goodSig = hash_hmac('sha512', json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'secret-ipn-test');

        // Corps envoyé dans un ordre quelconque : la signature de la version triée est acceptée.
        $this->assertTrue($service->verifyWebhookSignature(json_encode($payload), $goodSig));
        $this->assertTrue($service->verifyWebhookSignature(json_encode($payload), strtoupper($goodSig)));
        $this->assertFalse($service->verifyWebhookSignature(json_encode($payload), 'faux'));
        $this->assertFalse($service->verifyWebhookSignature(json_encode($payload), ''));
        $this->assertFalse($service->verifyWebhookSignature(json_encode(array_merge($payload, ['payment_status' => 'failed'])), $goodSig), 'corps modifié');
    }

    public function test_creation_de_facture(): void
    {
        Http::fake(['api.nowpayments.io/v1/invoice' => Http::response(['id' => '777', 'order_id' => 'CMD-1', 'invoice_url' => 'https://nowpayments.io/payment/?iid=777'], 200)]);

        $session = (new NowPaymentsService())->createInvoice(['order_id' => 'CMD-1', 'amount_xof' => 20000, 'return_url' => 'https://exemple.test/retour']);

        $this->assertSame('777', $session['invoice_id']);
        $this->assertSame('https://nowpayments.io/payment/?iid=777', $session['payment_url']);
        Http::assertSent(fn ($r) => $r->hasHeader('x-api-key', 'cle-api-test') && $r['price_amount'] == 29.9 && $r['price_currency'] === 'usd' && $r['order_id'] === 'CMD-1'
            && $r['is_fixed_rate'] === true && $r['is_fee_paid_by_user'] === true);
    }

    public function test_les_frais_peuvent_etre_remis_a_la_charge_du_marchand(): void
    {
        config(['services.nowpayments.fees_paid_by_user' => false]);
        Http::fake(['api.nowpayments.io/v1/invoice' => Http::response(['id' => '778', 'invoice_url' => 'https://nowpayments.io/payment/?iid=778'], 200)]);

        (new NowPaymentsService())->createInvoice(['order_id' => 'CMD-2', 'amount_xof' => 20000, 'return_url' => 'https://exemple.test/retour']);

        Http::assertSent(fn ($r) => !isset($r['is_fixed_rate']) && !isset($r['is_fee_paid_by_user']));
    }

    public function test_le_montant_inferieur_au_facture_est_detecte(): void
    {
        $service = new NowPaymentsService();
        $this->assertFalse($service->isUnderpaid(20000, ['price_amount' => 29.9, 'price_currency' => 'usd']));
        $this->assertFalse($service->isUnderpaid(20000, ['price_amount' => 29.0, 'price_currency' => 'usd']), 'tolérance sur le taux');
        $this->assertTrue($service->isUnderpaid(20000, ['price_amount' => 5.0, 'price_currency' => 'usd']));
        $this->assertTrue($service->isUnderpaid(20000, ['price_amount' => 29.9, 'price_currency' => 'eur']));
        $this->assertTrue($service->isUnderpaid(20000, []));
    }

    // ─── IPN : commandes ──────────────────────────────────────────────────────────

    public function test_signature_invalide_rejetee_sans_changement(): void
    {
        $order = $this->makeOrder('NP-SIG');

        $this->postJson('/api/webhooks/nowpayments', ['order_id' => 'NP-SIG', 'payment_id' => 1], ['x-nowpayments-sig' => 'faux'])->assertStatus(403);

        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_ipn_valide_relit_l_etat_et_marque_la_commande_payee(): void
    {
        $order = $this->makeOrder('NP-PAID');
        $this->service()->shouldReceive('getPayment')->with('5001')->once()->andReturn($this->verified('NP-PAID', 'finished'));

        $this->ipn('NP-PAID')->assertOk()->assertJson(['status' => 'order_synced']);

        $fresh = $order->fresh();
        $this->assertNotNull($fresh->paid_at);
        $this->assertSame('nowpayments', $fresh->paid_with);
        $this->assertSame('5001', $fresh->payment_checkout_id, 'identifiant de paiement mémorisé pour la relecture au retour');
    }

    public function test_le_statut_du_corps_de_l_ipn_n_est_jamais_cru(): void
    {
        $order = $this->makeOrder('NP-LIE');
        // L'IPN prétend « finished », mais NOWPayments répond « waiting ».
        $this->service()->shouldReceive('getPayment')->andReturn($this->verified('NP-LIE', 'waiting'));

        $this->ipn('NP-LIE', ['payment_status' => 'finished'])->assertOk();

        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_reponse_nowpayments_d_une_autre_commande_est_ignoree(): void
    {
        $order = $this->makeOrder('NP-A');
        $this->service()->shouldReceive('getPayment')->andReturn($this->verified('NP-AUTRE', 'finished'));

        $this->ipn('NP-A')->assertOk();

        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_notification_rejouee_un_seul_evenement_paye(): void
    {
        $order = $this->makeOrder('NP-REPLAY');
        $this->service()->shouldReceive('getPayment')->andReturn($this->verified('NP-REPLAY', 'finished'));

        for ($i = 0; $i < 3; $i++) {
            $this->ipn('NP-REPLAY')->assertOk();
        }

        $this->assertSame(1, OrderEvent::where('order_id', $order->id)->where('type', 'paid')->count());
    }

    public function test_paiement_tardif_sur_commande_expiree_est_repris(): void
    {
        $order = $this->makeOrder('NP-LATE', ['status' => 'expired']);
        $this->service()->shouldReceive('getPayment')->andReturn($this->verified('NP-LATE', 'finished'));

        $this->ipn('NP-LATE')->assertOk();

        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertNotSame('expired', $order->fresh()->status);
    }

    public function test_echec_ne_defait_pas_une_commande_payee_et_marque_une_commande_en_attente(): void
    {
        $paid = $this->makeOrder('NP-OK');
        $failing = $this->makeOrder('NP-KO');
        $service = $this->service();
        $service->shouldReceive('getPayment')->andReturnUsing(fn () => $this->verified(request()->input('order_id'), request()->input('payment_status') === 'finished' ? 'finished' : 'failed'));

        $this->ipn('NP-OK')->assertOk();
        $this->ipn('NP-OK', ['payment_status' => 'failed'])->assertOk();
        $this->ipn('NP-KO', ['payment_status' => 'failed'])->assertOk();

        $this->assertNotNull($paid->fresh()->paid_at);
        $this->assertNotSame('payment_failed', $paid->fresh()->status);
        $this->assertSame('payment_failed', $failing->fresh()->status);
    }

    public function test_paiement_partiel_ou_en_cours_ne_change_rien(): void
    {
        $order = $this->makeOrder('NP-PART');
        $service = $this->service();
        foreach (['waiting', 'confirming', 'confirmed', 'sending', 'partially_paid'] as $status) {
            $service->shouldReceive('getPayment')->once()->andReturn($this->verified('NP-PART', $status));
            $this->ipn('NP-PART')->assertOk();
        }

        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_montant_ou_devise_insuffisant_ne_livre_pas(): void
    {
        $under = $this->makeOrder('NP-UNDER');
        $eur = $this->makeOrder('NP-EUR');
        $service = $this->service();
        $service->shouldReceive('getPayment')->with('5001')->andReturnUsing(fn () => $this->verified(request()->input('order_id'), 'finished',
            request()->input('order_id') === 'NP-UNDER' ? ['price_amount' => 1.0] : ['price_currency' => 'eur']));

        $this->ipn('NP-UNDER')->assertOk();
        $this->ipn('NP-EUR')->assertOk();

        $this->assertSame('amount_mismatch', $under->fresh()->failure_code);
        $this->assertSame('amount_mismatch', $eur->fresh()->failure_code);
        $this->assertNull($under->fresh()->delivery_encrypted);
    }

    public function test_relecture_impossible_demande_de_reessayer_et_ne_change_rien(): void
    {
        $order = $this->makeOrder('NP-RETRY');
        $this->service()->shouldReceive('getPayment')->andReturn(['error' => 'timeout']);

        $this->ipn('NP-RETRY')->assertStatus(503);

        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_ipn_inconnu_ou_incomplet(): void
    {
        $this->service();
        $this->ipn('INCONNU')->assertStatus(404);
        $this->postJson('/api/webhooks/nowpayments', ['order_id' => 'X'])->assertStatus(400);
    }

    public function test_une_commande_d_une_autre_passerelle_n_est_pas_touchee_par_un_ipn(): void
    {
        $order = $this->makeOrder('NP-CINET', ['paid_with' => 'cinetpay']);
        $this->service();

        $this->ipn('NP-CINET')->assertStatus(404);

        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    // ─── IPN : recharges ──────────────────────────────────────────────────────────

    public function test_recharge_creditee_une_seule_fois(): void
    {
        $deposit = $this->makeDeposit('NP-DEP');
        $this->service()->shouldReceive('getPayment')->andReturn($this->verified('NP-DEP', 'finished', ['price_amount' => 7.47]));

        for ($i = 0; $i < 3; $i++) {
            $this->ipn('NP-DEP')->assertOk()->assertJson(['status' => 'deposit_synced']);
        }

        $this->assertSame(6000, $deposit->user->fresh()->balance_xof);
        $this->assertSame(1, WalletEntry::where('reference_id', $deposit->id)->count());
        $this->assertSame('completed', $deposit->fresh()->status);
    }

    public function test_recharge_echouee_puis_ipn_paye_ne_credite_pas(): void
    {
        $deposit = $this->makeDeposit('NP-DEP-F');
        $service = $this->service();
        $service->shouldReceive('getPayment')->once()->andReturn($this->verified('NP-DEP-F', 'expired'));
        $this->ipn('NP-DEP-F')->assertOk();
        $service->shouldReceive('getPayment')->andReturn($this->verified('NP-DEP-F', 'finished', ['price_amount' => 7.47]));
        $this->ipn('NP-DEP-F')->assertOk();

        $this->assertSame(1000, $deposit->user->fresh()->balance_xof);
        $this->assertSame('failed', $deposit->fresh()->status);
    }

    public function test_recharge_en_sous_paiement_ne_credite_rien(): void
    {
        $deposit = $this->makeDeposit('NP-DEP-U', 50000);
        $this->service()->shouldReceive('getPayment')->andReturn($this->verified('NP-DEP-U', 'finished', ['price_amount' => 0.5]));

        $this->ipn('NP-DEP-U')->assertOk();

        $this->assertSame(1000, $deposit->user->fresh()->balance_xof);
        $this->assertSame('failed', $deposit->fresh()->status);
    }

    // ─── Parcours client ──────────────────────────────────────────────────────────

    public function test_checkout_crypto_cree_la_commande_et_redirige_vers_la_facture(): void
    {
        $user = User::factory()->create(['country' => 'BF']);
        $product = Product::create([
            'id' => (string) Str::uuid(), 'supplier_id' => 'SUP-NP', 'slug' => 'np-' . Str::random(5), 'name' => 'Produit crypto',
            'category' => 'Bureautique', 'cost_usd_cents' => 300, 'price_xof_override' => 15000,
            'is_published' => true, 'supplier_active' => true, 'in_stock' => true,
        ]);
        $mock = Mockery::mock(NowPaymentsService::class)->makePartial();
        $mock->shouldReceive('createInvoice')->once()->andReturn(['invoice_id' => '888', 'payment_url' => 'https://nowpayments.io/payment/?iid=888', 'amount_usd' => 22.42]);
        $this->app->instance(NowPaymentsService::class, $mock);

        $this->actingAs($user, 'web')->withHeaders(['X-Inertia' => 'true'])->post(route('checkout.store'), [
            'product_id' => $product->id, 'quantity' => 1, 'expectedTotalXof' => 15000, 'country' => 'BF', 'payWith' => 'nowpayments',
        ])->assertStatus(409);

        $order = Order::where('user_id', $user->id)->first();
        $this->assertSame('nowpayments', $order->paid_with);
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame('inv:888', $order->payment_checkout_id);
        $this->assertSame('https://nowpayments.io/payment/?iid=888', $order->payment_url);
    }

    public function test_recharge_crypto_cree_la_recharge_avec_sa_passerelle(): void
    {
        $user = User::factory()->create(['country' => 'BF']);
        $mock = Mockery::mock(NowPaymentsService::class)->makePartial();
        $mock->shouldReceive('createInvoice')->once()->andReturn(['invoice_id' => '889', 'payment_url' => 'https://nowpayments.io/payment/?iid=889', 'amount_usd' => 7.47]);
        $this->app->instance(NowPaymentsService::class, $mock);

        $this->actingAs($user, 'web')->withHeaders(['X-Inertia' => 'true'])->post('/compte/solde/recharger', [
            'amount_xof' => 5000, 'country' => 'BF', 'gateway' => 'nowpayments',
        ])->assertStatus(409);

        $deposit = WalletDeposit::where('user_id', $user->id)->first();
        $this->assertSame('nowpayments', $deposit->gateway);
        $this->assertSame('pending', $deposit->status);
        $this->assertSame('inv:889', $deposit->payment_checkout_id);
    }
}
