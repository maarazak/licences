<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStockItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\FlowsellerService;
use App\Services\OrderFulfillmentService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/** Produits manuels : créés depuis le backoffice, stock propre, livrés sans fournisseur, jamais touchés par la synchronisation. */
class ManualProductsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::clearRuntimeCache();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
    }

    private function create(array $extra = []): Product
    {
        $payload = array_merge([
            'name' => 'Netflix Premium 1 mois', 'accessKind' => 'account', 'priceXof' => 5000, 'shopCategory' => 'Divertissement',
            'durationLabel' => '1 mois', 'warranty' => '7 jours', 'isPublished' => '1',
            'stockItems' => "a@x.com:pass1\nb@x.com:pass2\n\na@x.com:pass1\nc@x.com:pass3",
        ], $extra);
        // Une case non cochée n'est pas envoyée par le navigateur : une valeur null retire le champ.
        $payload = array_filter($payload, fn ($value) => $value !== null);
        $this->actingAs($this->admin, 'admin')->post(route('admin.products.store'), $payload)->assertRedirect();

        return Product::where('source', 'manual')->latest('created_at')->firstOrFail();
    }

    private function order(Product $product, int $quantity = 1): Order
    {
        return Order::create([
            'id' => (string) Str::uuid(), 'reference' => 'CMD-' . Str::upper(Str::random(8)), 'user_id' => User::factory()->create()->id,
            'product_id' => $product->id, 'product_name' => $product->title, 'supplier_product_id' => $product->supplier_id,
            'quantity' => $quantity, 'unit_price_xof' => 5000, 'total_xof' => 5000 * $quantity, 'status' => 'paid',
        ]);
    }

    public function test_creation_depuis_le_backoffice_avec_stock_chiffre_et_dedoublonne(): void
    {
        $product = $this->create();

        $this->assertTrue($product->isManual());
        $this->assertStringStartsWith('manual-', $product->supplier_id);
        $this->assertSame(5000, $product->price_xof);
        $this->assertTrue($product->is_published);
        $this->assertSame(3, $product->stock, 'lignes vides et doublons ignorés');
        $this->assertTrue($product->in_stock && $product->supplier_purchasable && $product->supplier_active && !$product->preorder);
        $raw = ProductStockItem::where('product_id', $product->id)->pluck('content_encrypted')->implode(' ');
        $this->assertStringNotContainsString('pass1', $raw, 'stock chiffré au repos');
    }

    public function test_le_produit_manuel_apparait_sur_le_site_comme_disponible(): void
    {
        $product = $this->create(['name' => 'Spotify 3 mois']);

        $names = collect($this->get('/boutique')->assertOk()->viewData('page')['props']['products'])->where('slug', $product->slug)->first();

        $this->assertNotNull($names);
        $this->assertTrue($names['available']);
        $this->assertSame(3, $names['stock']);
    }

    public function test_la_synchronisation_ne_modifie_ni_ne_desactive_les_produits_manuels(): void
    {
        $product = $this->create();
        $product->update(['stock' => 3, 'price_xof_override' => 5000]);

        $fake = Mockery::mock(FlowsellerService::class)->makePartial();
        $fake->shouldReceive('isConfigured')->andReturn(true);
        $fake->shouldReceive('listAllProducts')->andReturn([
            'products' => [['id' => 'S-1', 'name' => 'Autre produit', 'price' => 3.0, 'in_stock' => true, 'purchasable' => true]],
            'seenIds' => ['S-1'], 'invalid' => 0,
        ]);
        $this->app->instance(FlowsellerService::class, $fake);

        app(CatalogService::class)->syncCatalog();

        $fresh = $product->fresh();
        $this->assertTrue($fresh->supplier_active && $fresh->is_published && $fresh->in_stock);
        $this->assertSame(3, $fresh->stock);
        $this->assertSame(5000, $fresh->price_xof);
        $this->assertSame('Netflix Premium 1 mois', $fresh->name);
    }

    public function test_publier_tout_ne_publie_pas_les_produits_manuels_masques(): void
    {
        $product = $this->create(['isPublished' => null]);
        $this->assertFalse($product->is_published);

        $this->actingAs($this->admin, 'admin')->post(route('admin.products.publish_all'))->assertRedirect();

        $this->assertFalse($product->fresh()->is_published);
    }

    public function test_livraison_depuis_le_stock_sans_aucun_appel_fournisseur(): void
    {
        $product = $this->create();
        $fake = Mockery::mock(FlowsellerService::class)->makePartial();
        $fake->shouldNotReceive('createOrder');
        $this->app->instance(FlowsellerService::class, $fake);
        $order = $this->order($product, 2);

        app(OrderFulfillmentService::class)->fulfil($order->id);

        $fresh = $order->fresh();
        $this->assertSame('delivered', $fresh->status);
        $delivered = json_decode(\App\Services\CryptoService::decrypt($fresh->delivery_encrypted, $order->id), true);
        $this->assertCount(2, $delivered);
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertSame(2, ProductStockItem::where('order_id', $order->id)->where('status', 'sold')->count());
    }

    public function test_deux_commandes_pour_le_dernier_acces_une_seule_est_livree_et_aucun_acces_n_est_double(): void
    {
        $product = $this->create(['stockItems' => 'unique@x.com:secret']);
        $a = $this->order($product);
        $b = $this->order($product);

        app(OrderFulfillmentService::class)->fulfil($a->id);
        app(OrderFulfillmentService::class)->fulfil($b->id);

        $this->assertSame('delivered', $a->fresh()->status);
        $this->assertSame('failed', $b->fresh()->status);
        $this->assertSame('insufficient_stock', $b->fresh()->failure_code);
        $this->assertNull($b->fresh()->delivery_encrypted);
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertFalse($product->fresh()->in_stock);
    }

    public function test_une_livraison_rejouee_redonne_les_memes_acces_sans_en_consommer_d_autres(): void
    {
        $product = $this->create();
        $order = $this->order($product);
        $service = app(OrderFulfillmentService::class);

        $service->fulfil($order->id);
        $first = \App\Services\CryptoService::decrypt($order->fresh()->delivery_encrypted, $order->id);
        $order->update(['status' => 'paid', 'fulfil_locked_at' => null]);
        $service->fulfil($order->id);

        $this->assertSame($first, \App\Services\CryptoService::decrypt($order->fresh()->delivery_encrypted, $order->id));
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_ajout_et_retrait_de_stock_mettent_a_jour_la_disponibilite(): void
    {
        $product = $this->create(['stockItems' => 'seul@x.com:pw']);
        $this->actingAs($this->admin, 'admin');

        $item = $product->stockItems()->first();
        $this->post(route('admin.products.stock_remove', [$product->id, $item->id]))->assertRedirect();
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertFalse($product->fresh()->in_stock);

        $this->post(route('admin.products.stock_add', $product->id), ['stockItems' => "n1@x.com:a\nn2@x.com:b"])->assertRedirect();
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertTrue($product->fresh()->in_stock);
    }

    public function test_un_acces_deja_vendu_ne_se_retire_pas_et_un_produit_commande_ne_se_supprime_pas(): void
    {
        $product = $this->create();
        $order = $this->order($product);
        app(OrderFulfillmentService::class)->fulfil($order->id);
        $sold = $product->stockItems()->where('status', 'sold')->first();
        $this->actingAs($this->admin, 'admin');

        $this->post(route('admin.products.stock_remove', [$product->id, $sold->id]))->assertSessionHas('error');
        $this->assertNotNull(ProductStockItem::find($sold->id));

        $this->post(route('admin.products.destroy', $product->id))->assertSessionHas('error');
        $this->assertNotNull(Product::find($product->id));
    }

    public function test_un_produit_manuel_jamais_commande_peut_etre_supprime_avec_son_stock(): void
    {
        $product = $this->create();

        $this->actingAs($this->admin, 'admin')->post(route('admin.products.destroy', $product->id))->assertRedirect(route('admin.products.index'));

        $this->assertNull(Product::find($product->id));
        $this->assertSame(0, ProductStockItem::where('product_id', $product->id)->count());
    }

    public function test_les_produits_fournisseur_ne_sont_pas_concernes_par_les_actions_de_stock(): void
    {
        $supplier = Product::create([
            'id' => (string) Str::uuid(), 'supplier_id' => 'SUP-1', 'slug' => 'sup-1', 'name' => 'Fournisseur', 'category' => 'x',
            'cost_usd_cents' => 100, 'is_published' => true, 'supplier_active' => true,
        ]);

        $this->actingAs($this->admin, 'admin')->post(route('admin.products.stock_add', $supplier->id), ['stockItems' => 'a:b'])->assertNotFound();
    }

    public function test_validation_du_formulaire(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.products.store'), ['name' => '', 'priceXof' => 0, 'accessKind' => 'inconnu'])
            ->assertSessionHasErrors(['name', 'priceXof', 'accessKind']);
        $this->assertSame(0, Product::where('source', 'manual')->count());
    }

    public function test_les_pages_admin_s_affichent(): void
    {
        $product = $this->create();
        $this->actingAs($this->admin, 'admin');

        $this->get(route('admin.products.create'))->assertOk()->assertSee('Nouveau produit');
        $this->get(route('admin.products.edit', $product->id))->assertOk()->assertSee('Ajouter au stock')->assertDontSee('pass1');
        $this->get(route('admin.products.index'))->assertOk()->assertSee('Manuel');
    }
}
