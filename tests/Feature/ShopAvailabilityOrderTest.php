<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Accueil et boutique : produits en stock d'abord, produits en rupture ensuite (jamais retirés). */
class ShopAvailabilityOrderTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, int $priceXof, bool $inStock = true): Product
    {
        return Product::create([
            'id' => (string) Str::uuid(), 'supplier_id' => 'SUP-' . Str::random(8), 'slug' => Str::slug($name) . '-' . Str::random(4),
            'name' => $name, 'category' => 'Bureautique', 'cost_usd_cents' => 100, 'price_xof_override' => $priceXof,
            'is_published' => true, 'supplier_active' => true, 'supplier_purchasable' => true, 'in_stock' => $inStock, 'preorder' => false,
        ]);
    }

    private function props(string $url): array
    {
        $props = [];
        $this->get($url)->assertOk()->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];

            return $page;
        });

        return $props;
    }

    private function seedCatalog(): void
    {
        Product::query()->delete();
        $this->product('Netflix en rupture', 2000, false);   // marque phare mais indisponible
        $this->product('Spotify 3M', 4000);                  // marque phare disponible
        $this->product('Antivirus rupture', 1000, false);
        $this->product('Antivirus dispo', 1500);
        $this->product('Zeta dispo', 3000);
    }

    public function test_l_accueil_met_les_produits_en_stock_avant_ceux_en_rupture_sans_en_retirer(): void
    {
        $this->seedCatalog();

        $names = collect($this->props('/')['products'])->pluck('name')->all();

        $this->assertSame(['Spotify 3M', 'Antivirus dispo', 'Zeta dispo'], array_slice($names, 0, 3), 'en stock d\'abord');
        $this->assertEqualsCanonicalizing(['Netflix en rupture', 'Antivirus rupture'], array_slice($names, 3), 'les ruptures suivent');
        $this->assertCount(5, $names);
    }

    public function test_la_liste_des_phares_de_l_accueil_garde_les_produits_en_stock_en_premier(): void
    {
        $this->seedCatalog();

        $featured = collect($this->props('/')['featuredRaw'])->pluck('name')->all();

        $this->assertSame(['Spotify 3M', 'Netflix en rupture'], $featured);
    }

    #[DataProvider('sorts')]
    public function test_la_boutique_met_les_produits_en_stock_avant_ceux_en_rupture_pour_chaque_tri(string $sort, array $expected): void
    {
        $this->seedCatalog();

        $names = collect($this->props('/boutique?tri=' . $sort)['products'])->pluck('name')->all();

        $this->assertSame($expected, $names);
    }

    public static function sorts(): array
    {
        return [
            'phares d\'abord' => ['featured', ['Spotify 3M', 'Antivirus dispo', 'Zeta dispo', 'Netflix en rupture', 'Antivirus rupture']],
            'prix croissant' => ['price_asc', ['Antivirus dispo', 'Zeta dispo', 'Spotify 3M', 'Antivirus rupture', 'Netflix en rupture']],
            'prix décroissant' => ['price_desc', ['Spotify 3M', 'Zeta dispo', 'Antivirus dispo', 'Netflix en rupture', 'Antivirus rupture']],
            'nom A-Z' => ['name', ['Antivirus dispo', 'Spotify 3M', 'Zeta dispo', 'Antivirus rupture', 'Netflix en rupture']],
        ];
    }

    public function test_le_filtre_en_stock_uniquement_reste_disponible(): void
    {
        $this->seedCatalog();

        $names = collect($this->props('/boutique?dispo=disponible')['products'])->pluck('name')->all();

        $this->assertSame(['Spotify 3M', 'Antivirus dispo', 'Zeta dispo'], $names);
    }
}
