<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Carrousel de l'accueil : sélection automatique par marque d'après le nom des produits, sans réglage manuel. */
class HomeHeroBrandsTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, int $priceXof, array $extra = []): Product
    {
        return Product::create(array_merge([
            'id' => (string) Str::uuid(), 'supplier_id' => 'SUP-' . Str::random(8), 'slug' => Str::slug($name) . '-' . Str::random(4),
            'name' => $name, 'category' => 'Bureautique', 'cost_usd_cents' => 100, 'price_xof_override' => $priceXof,
            'is_published' => true, 'supplier_active' => true, 'supplier_purchasable' => true, 'in_stock' => true, 'preorder' => false,
        ], $extra));
    }

    private function homeProps(): array
    {
        $page = null;
        $this->get('/')->assertOk()->assertInertia(function (Assert $inertia) use (&$page) {
            $page = $inertia->toArray()['props'];

            return $inertia->component('Shop/Home');
        });

        return $page;
    }

    public function test_le_carrousel_prend_le_meilleur_produit_disponible_de_chaque_marque_dans_l_ordre(): void
    {
        Product::query()->delete();
        $this->product('Canva Pro 1 Month', 3000);
        $this->product('ChatGPT Plus 1M', 5000);
        $this->product('Netflix Premium 4K', 8000);
        $this->product('Netflix Basic (épuisé)', 2000, ['in_stock' => false]);
        $this->product('Spotify 3M Link', 4000);
        $this->product('Spotify 12M', 9000);
        $this->product('Prime Video 6 Months', 4500);
        $this->product('API Claude 100$', 7000);
        $this->product('Apple Music 5M (épuisé)', 3500, ['in_stock' => false]);
        $this->product('Antivirus quelconque', 1500);

        $props = $this->homeProps();
        $names = collect($props['featured'])->pluck('name')->all();

        // Ordre des marques : Netflix, Spotify, Prime Video, Claude, ChatGPT, Canva. Apple Music est en rupture : absent.
        $this->assertSame(['Netflix Premium 4K', 'Spotify 3M Link', 'Prime Video 6 Months', 'API Claude 100$', 'ChatGPT Plus 1M', 'Canva Pro 1 Month'], $names);
        $this->assertNotContains('Antivirus quelconque', $names);
        $this->assertSame($names, collect($props['carouselProducts'])->pluck('name')->all());
    }

    public function test_les_produits_de_marque_deviennent_phares_et_passent_avant_les_autres(): void
    {
        Product::query()->delete();
        $this->product('Antivirus quelconque', 1500);
        $this->product('Spotify 3M Link', 4000);
        $this->product('Netflix Premium 4K', 8000);

        $props = $this->homeProps();

        $this->assertSame(['Netflix Premium 4K', 'Spotify 3M Link', 'Antivirus quelconque'], collect($props['products'])->pluck('name')->all());
        $this->assertSame(['Netflix Premium 4K', 'Spotify 3M Link'], collect($props['featuredRaw'])->pluck('name')->all());
    }

    public function test_les_produits_non_publies_ou_retires_ne_sont_jamais_mis_en_avant(): void
    {
        Product::query()->delete();
        $this->product('Netflix masqué', 8000, ['is_published' => false]);
        $this->product('Spotify retiré', 4000, ['supplier_active' => false]);

        $props = $this->homeProps();

        $this->assertSame([], $props['featured']);
    }

    public function test_un_rang_manuel_du_backoffice_reste_prioritaire(): void
    {
        Product::query()->delete();
        foreach (['Zeta A' => 1, 'Zeta B' => 2, 'Zeta C' => 3, 'Zeta D' => 4] as $name => $rank) {
            $this->product($name, 1000, ['is_featured' => true, 'featured_rank' => $rank]);
        }
        $this->product('Netflix Premium 4K', 8000);

        $props = $this->homeProps();

        $this->assertSame(['Zeta A', 'Zeta B', 'Zeta C', 'Zeta D'], collect($props['featured'])->pluck('name')->all());
    }

    public function test_sans_aucune_marque_le_carrousel_est_vide_sans_erreur(): void
    {
        Product::query()->delete();
        $this->product('Antivirus quelconque', 1500);

        $this->assertSame([], $this->homeProps()['featured']);
    }
}
