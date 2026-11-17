<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardAndPagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'email' => 'admin-fidelity@licences.pro',
            'role' => 'admin',
        ]);
    }

    public function test_admin_dashboard_renders_identical_to_nextjs(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Tableau de bord');
        $response->assertSee('Ventes livrées');
        $response->assertSee('24 dernières heures');
        $response->assertSee('7 derniers jours');
        $response->assertSee('30 derniers jours');
        $response->assertSee('Solde fournisseur');
        $response->assertSee('Commandes en cours');
        $response->assertSee('Demandes ouvertes');
        $response->assertSee('Catalogue');
        $response->assertSee('Synchroniser maintenant');
    }

    public function test_admin_products_catalog_with_actions(): void
    {
        $product = Product::create([
            'id' => (string) Str::uuid(),
            'supplier_id' => 'supp-101',
            'slug' => 'chatgpt-plus-test',
            'name' => 'ChatGPT Plus (1 mois)',
            'category' => 'ia',
            'shop_category' => 'IA et assistants',
            'cost_usd_cents' => 800,
            'is_published' => false,
            'supplier_active' => true,
            'supplier_purchasable' => true,
            'in_stock' => true,
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get('/admin/produits');

        $response->assertStatus(200);
        $response->assertSee('Produits');
        $response->assertSee('Synchroniser');
        $response->assertSee('Publier tout le vendable');
        $response->assertSee('ChatGPT Plus (1 mois)');
        $response->assertSee('Publier');

        // Test publier tout le vendable
        $publishResponse = $this->actingAs($this->admin, 'admin')
            ->post('/admin/produits/publier-tout');

        $publishResponse->assertSessionHas('success');
        $this->assertTrue((bool) $product->fresh()->is_published);
    }

    public function test_admin_orders_page_with_filters(): void
    {
        $order = Order::create([
            'id' => (string) Str::uuid(),
            'reference' => 'CMD-FIDEL-001',
            'user_id' => $this->admin->id,
            'product_name' => 'Canva Pro 1 an',
            'quantity' => 1,
            'unit_price_xof' => 5000,
            'total_xof' => 5000,
            'unit_cost_usd_cents' => 300,
            'usd_rate' => 669,
            'status' => 'failed',
            'customer_email' => 'client@test.ci',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get('/admin/commandes?filtre=a-traiter');

        $response->assertStatus(200);
        $response->assertSee('Commandes');
        $response->assertSee('À traiter');
        $response->assertSee('CMD-FIDEL-001');

        // Detail page
        $showResponse = $this->actingAs($this->admin, 'admin')->get("/admin/commandes/{$order->id}");
        $showResponse->assertStatus(200);
        $showResponse->assertSee('CMD-FIDEL-001');
        $showResponse->assertSee('Relancer la livraison');
        $showResponse->assertSee('Créditer le solde du client');
    }

    public function test_admin_clients_page(): void
    {
        $client = User::create([
            'id' => (string) Str::uuid(),
            'email' => 'client-fidelity@licences.pro',
            'role' => 'client',
            'balance_xof' => 12500,
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get('/admin/clients');

        $response->assertStatus(200);
        $response->assertSee('Clients');
        $response->assertSee('client-fidelity@licences.pro');
        $response->assertSee('12 500 FCFA');
    }

    public function test_admin_assistance_tickets_page(): void
    {
        $ticket = Ticket::create([
            'id' => (string) Str::uuid(),
            'reference' => 'TK-FIDEL-001',
            'user_id' => $this->admin->id,
            'subject' => 'Problème de connexion VPN',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get('/admin/assistance');

        $response->assertStatus(200);
        $response->assertSee('Assistance');
        $response->assertSee('Problème de connexion VPN');
        $response->assertSee('En attente de réponse');
    }

    public function test_admin_journal_page(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/journal');

        $response->assertStatus(200);
        $response->assertSee('Journal');
        $response->assertSee('Les 300 dernières actions effectuées dans le backoffice.');
    }
}
