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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

/** Produits manuels : créés depuis le backoffice, stock propre, livrés sans fournisseur, jamais touchés par la synchronisation. */
class ManualProductsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();
        // Les images envoyées pendant les tests vont dans un dossier temporaire, jamais dans public/.
        $this->publicDir = sys_get_temp_dir() . '/licences-tests-' . Str::random(8);
        File::ensureDirectoryExists($this->publicDir);
        $this->app->usePublicPath($this->publicDir);
        $this->withoutVite();
        Setting::clearRuntimeCache();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    private function create(array $extra = []): Product
    {
        $payload = array_merge([
            'name' => 'Netflix Premium 1 mois', 'accessKind' => 'account', 'priceXof' => 5000, 'shopCategory' => 'Divertissement',
            'durationLabel' => '1 mois', 'warranty' => '7 days', 'isPublished' => '1',
            'stockItems' => "a@x.com:pass1\nb@x.com:pass2\n\na@x.com:pass1\nc@x.com:pass3",
        ], $extra);
        // Une case non cochée n'est pas envoyée par le navigateur : une valeur null retire le champ.
        $payload = array_filter($payload, fn ($value) => $value !== null);
        $response = $this->actingAs($this->admin, 'admin')->post(route('admin.products.store'), $payload)->assertRedirect();

        // Le produit créé est celui vers lequel on est redirigé (plusieurs produits peuvent avoir la même seconde de création).
        return Product::findOrFail(basename((string) parse_url($response->headers->get('Location'), PHP_URL_PATH)));
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

    // ─── Image du produit ─────────────────────────────────────────────────────────

    public function test_creation_avec_image_stockee_sous_un_nom_aleatoire_et_exposee_au_site(): void
    {
        $product = $this->create(['image' => UploadedFile::fake()->image('mon-logo.png', 200, 200)]);

        $this->assertMatchesRegularExpression('#^/uploads/products/[0-9a-f-]{36}\.png$#', $product->image_url);
        $this->assertFileExists($this->publicDir . $product->image_url);
        $this->assertStringNotContainsString('mon-logo', $product->image_url, 'le nom envoyé n\'est jamais conservé');

        $card = collect($this->get('/boutique')->viewData('page')['props']['products'])->firstWhere('slug', $product->slug);
        $this->assertSame($product->image_url, $card['custom_image']);
        $home = collect($this->get('/')->viewData('page')['props']['products'])->firstWhere('slug', $product->slug);
        $this->assertSame($product->image_url, $home['custom_image']);
    }

    public function test_les_fichiers_non_images_svg_et_trop_lourds_sont_refuses(): void
    {
        $this->actingAs($this->admin, 'admin');
        $base = ['name' => 'Produit', 'accessKind' => 'account', 'priceXof' => 1000];

        $this->post(route('admin.products.store'), $base + ['image' => UploadedFile::fake()->create('script.php', 10, 'application/x-php')])->assertSessionHasErrors('image');
        $this->post(route('admin.products.store'), $base + ['image' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])->assertSessionHasErrors('image');
        $this->post(route('admin.products.store'), $base + ['image' => UploadedFile::fake()->image('gros.png', 500, 500)->size(4096)])->assertSessionHasErrors('image');
        $this->post(route('admin.products.store'), $base + ['image' => UploadedFile::fake()->image('petit.png', 16, 16)])->assertSessionHasErrors('image');

        $this->assertSame(0, Product::where('source', 'manual')->count());
    }

    public function test_remplacer_puis_retirer_l_image_supprime_l_ancien_fichier(): void
    {
        $product = $this->create(['image' => UploadedFile::fake()->image('a.png', 200, 200)]);
        $old = $this->publicDir . $product->image_url;
        $this->actingAs($this->admin, 'admin');
        $form = ['name' => $product->name, 'accessKind' => 'account', 'priceXofOverride' => 5000, 'isPublished' => '1'];

        $this->post(route('admin.products.update', $product->id), $form + ['image' => UploadedFile::fake()->image('b.jpg', 200, 200)])->assertRedirect();
        $new = $product->fresh()->image_url;
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($this->publicDir . $new);
        $this->assertStringEndsWith('.jpg', $new);

        $this->post(route('admin.products.update', $product->id), $form + ['removeImage' => '1'])->assertRedirect();
        $this->assertNull($product->fresh()->image_url);
        $this->assertFileDoesNotExist($this->publicDir . $new);
    }

    public function test_modifier_le_produit_sans_toucher_a_l_image_la_conserve(): void
    {
        $product = $this->create(['image' => UploadedFile::fake()->image('a.png', 200, 200)]);
        $image = $product->image_url;

        $this->actingAs($this->admin, 'admin')->post(route('admin.products.update', $product->id), [
            'name' => 'Nouveau nom', 'accessKind' => 'link', 'priceXofOverride' => 7000, 'isPublished' => '1', 'shopCategory' => 'IA et assistants',
        ])->assertRedirect();

        $fresh = $product->fresh();
        $this->assertSame($image, $fresh->image_url);
        $this->assertSame('Nouveau nom', $fresh->title);
        $this->assertSame(7000, $fresh->price_xof);
        $this->assertSame('link', $fresh->delivery_kind);
    }

    public function test_la_suppression_du_produit_supprime_son_image(): void
    {
        $product = $this->create(['image' => UploadedFile::fake()->image('a.png', 200, 200)]);
        $file = $this->publicDir . $product->image_url;

        $this->actingAs($this->admin, 'admin')->post(route('admin.products.destroy', $product->id))->assertRedirect();

        $this->assertFileDoesNotExist($file);
    }

    public function test_un_produit_fournisseur_n_expose_jamais_d_image_personnalisee(): void
    {
        $supplier = Product::create([
            'id' => (string) Str::uuid(), 'supplier_id' => 'SUP-IMG', 'slug' => 'sup-img', 'name' => 'Fournisseur', 'category' => 'x',
            'cost_usd_cents' => 100, 'is_published' => true, 'supplier_active' => true, 'supplier_purchasable' => true, 'in_stock' => true,
            'image_url' => 'https://exemple.test/image-du-fournisseur.png',
        ]);

        $card = collect($this->get('/boutique')->viewData('page')['props']['products'])->firstWhere('slug', $supplier->slug);

        $this->assertNull($card['custom_image']);
    }

    // ─── Modification d'un accès du stock ─────────────────────────────────────────

    public function test_modifier_un_acces_disponible_change_ce_qui_sera_livre(): void
    {
        $product = $this->create(['stockItems' => 'ancien@x.com:ancien']);
        $item = $product->stockItems()->first();
        $this->actingAs($this->admin, 'admin')->post(route('admin.products.stock_update', [$product->id, $item->id]), ['content' => '  nouveau@x.com:nouveau  '])->assertSessionHas('success');

        $order = $this->order($product);
        app(OrderFulfillmentService::class)->fulfil($order->id);

        $delivered = json_decode(\App\Services\CryptoService::decrypt($order->fresh()->delivery_encrypted, $order->id), true);
        $this->assertSame(['nouveau@x.com:nouveau'], $delivered);
    }

    public function test_un_acces_deja_vendu_ne_se_modifie_pas_et_un_acces_d_un_autre_produit_non_plus(): void
    {
        $product = $this->create(['stockItems' => "a:1\nb:2"]);
        $other = $this->create(['name' => 'Autre', 'stockItems' => 'z:9']);
        $order = $this->order($product);
        app(OrderFulfillmentService::class)->fulfil($order->id);
        $sold = $product->stockItems()->where('status', 'sold')->first();
        $foreign = $other->stockItems()->first();
        $this->actingAs($this->admin, 'admin');

        $this->post(route('admin.products.stock_update', [$product->id, $sold->id]), ['content' => 'pirate'])->assertSessionHas('error');
        $this->post(route('admin.products.stock_update', [$product->id, $foreign->id]), ['content' => 'pirate'])->assertSessionHas('error');

        $this->assertSame('z:9', $foreign->fresh()->plain());
        $this->assertNotSame('pirate', $sold->fresh()->plain());
    }

    // ─── Menus déroulants : catégorie, durée, garantie ────────────────────────────

    public function test_catégorie_durée_et_garantie_n_acceptent_que_les_valeurs_des_listes(): void
    {
        $this->actingAs($this->admin, 'admin');
        $base = ['name' => 'Produit', 'accessKind' => 'account', 'priceXof' => 1000];

        foreach (['shopCategory' => 'Catégorie inventée', 'durationLabel' => '17 siècles', 'warranty' => 'Garantie illimitée'] as $field => $bad) {
            $this->post(route('admin.products.store'), $base + [$field => $bad])->assertSessionHasErrors($field);
        }
        $this->assertSame(0, Product::where('source', 'manual')->count());

        $this->post(route('admin.products.store'), $base + ['shopCategory' => 'Autres', 'durationLabel' => 'À vie', 'warranty' => 'Full Warranty'])->assertRedirect();
        $created = Product::where('source', 'manual')->firstOrFail();
        $this->assertSame(['Autres', 'À vie', 'Full Warranty'], [$created->shop_category, $created->duration_label, $created->warranty]);
    }

    public function test_la_page_nouveau_produit_propose_des_menus_deroulants(): void
    {
        $html = $this->actingAs($this->admin, 'admin')->get(route('admin.products.create'))->assertOk()->getContent();

        foreach (['shopCategory', 'durationLabel', 'warranty'] as $field) {
            $this->assertMatchesRegularExpression('#<select id="' . $field . '" name="' . $field . '"#', $html, "{$field} doit être un menu déroulant");
            $this->assertDoesNotMatchRegularExpression('#<input[^>]*name="' . $field . '"#', $html, "{$field} ne doit plus être un champ libre");
        }
        foreach (['IA et assistants', 'Divertissement', '3 mois', 'À vie', 'Aucune garantie', 'Garantie sur toute la durée'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    public function test_la_valeur_actuelle_hors_liste_d_un_ancien_produit_reste_selectionnee(): void
    {
        $product = $this->create();
        $product->update(['shop_category' => 'Ancienne catégorie libre', 'duration_label' => '5 ans']);

        $html = $this->actingAs($this->admin, 'admin')->get(route('admin.products.edit', $product->id))->assertOk()->getContent();

        $this->assertStringContainsString('<option value="Ancienne catégorie libre" selected>', $html);
        $this->assertStringContainsString('<option value="5 ans" selected>', $html);
    }

    public function test_les_durees_et_garanties_choisies_sont_affichees_au_client(): void
    {
        $product = $this->create(['durationLabel' => '3 mois', 'warranty' => 'No Warranty']);

        $card = collect($this->get('/boutique')->viewData('page')['props']['products'])->firstWhere('slug', $product->slug);

        $this->assertSame('3 mois', $card['duration']);
        $this->assertSame('No Warranty', $card['warranty']);
    }
}
