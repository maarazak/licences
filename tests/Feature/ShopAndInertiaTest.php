<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopAndInertiaTest extends TestCase
{
    public function test_home_page_renders_with_inertia(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Home')
            ->has('featured')
            ->has('categories')
        );
    }

    public function test_catalog_index_renders_with_inertia(): void
    {
        $response = $this->get('/boutique');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Index')
            ->has('products')
            ->has('categories')
            ->where('activeCategory', 'toutes')
        );
    }

    public function test_product_detail_renders_with_inertia(): void
    {
        $product = Product::where('is_published', true)->first();
        if (!$product) {
            $this->markTestSkipped('No published product found.');
        }

        $response = $this->get("/boutique/{$product->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Show')
            ->has('product')
            ->where('product.slug', $product->slug)
        );
    }

    public function test_client_login_page_renders_with_inertia(): void
    {
        $response = $this->get('/connexion');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
        );
    }

    public function test_client_code_request_creates_login_code(): void
    {
        $email = 'test.client.' . uniqid() . '@example.com';

        $response = $this->post('/connexion/demander-code', [
            'email' => $email,
        ]);

        $response->assertSessionHas('step', 'code');
        $this->assertDatabaseHas('login_codes', [
            'email' => $email,
        ]);
    }

    public function test_static_pages_render_with_inertia(): void
    {
        $this->get('/comment-ca-marche')->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Shop/HowItWorks'));

        $this->get('/assistance')->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Shop/Support'));

        $this->get('/conditions')->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Shop/Terms'));

        $this->get('/confidentialite')->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Shop/Privacy'));

        $this->get('/mentions-legales')->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Shop/Legal'));
    }

    public function test_admin_backoffice_blade_remains_intact_and_accessible(): void
    {
        $response = $this->get('/admin/connexion');
        $response->assertStatus(200);
        $response->assertSee('Backoffice Administrateur');

        $loginResponse = $this->post('/admin/connexion', [
            'email' => 'abdoulrazakmaali@gmail.com',
            'password' => 'Admin123!',
        ]);

        $loginResponse->assertRedirect('/admin');
    }
}
