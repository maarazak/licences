<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Services\CatalogService;
use App\Services\FlowsellerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Publication automatique des nouveaux produits synchronisés : « 1 », « true » et « on » activent, « 0 » et « false » désactivent. */
class CatalogAutoPublishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::clearRuntimeCache(); // le cache de réglages est statique : il survit d'un test à l'autre
    }

    private function sync(): array
    {
        $items = [
            ['id' => 'S-1', 'name' => 'Netflix Premium', 'price' => 3.0, 'in_stock' => true, 'purchasable' => true, 'preorder' => false],
            ['id' => 'S-2', 'name' => 'Produit non vendable', 'price' => 0, 'in_stock' => false, 'purchasable' => false],
        ];
        $fake = Mockery::mock(FlowsellerService::class)->makePartial();
        $fake->shouldReceive('isConfigured')->andReturn(true);
        $fake->shouldReceive('listAllProducts')->andReturn(['products' => $items, 'seenIds' => ['S-1', 'S-2'], 'invalid' => 0]);
        $this->app->instance(FlowsellerService::class, $fake);
        Setting::clearRuntimeCache();

        return app(CatalogService::class)->syncCatalog();
    }

    #[DataProvider('enabledValues')]
    public function test_les_valeurs_vraies_publient_les_nouveaux_produits(string $key, string $value): void
    {
        Setting::set($key, $value);

        $report = $this->sync();

        $this->assertTrue((bool) Product::where('supplier_id', 'S-1')->value('is_published'), json_encode($report));
        $this->assertNull(Product::where('supplier_id', 'S-2')->first(), 'un produit non vendable n\'est pas créé');
    }

    public static function enabledValues(): array
    {
        return [
            'case cochée du backoffice (1)' => ['autoPublish', '1'],
            'texte true' => ['autoPublish', 'true'],
            'clé snake_case' => ['auto_publish', '1'],
            'on' => ['autoPublish', 'on'],
        ];
    }

    #[DataProvider('disabledValues')]
    public function test_les_valeurs_fausses_ne_publient_pas(?string $value): void
    {
        if ($value !== null) {
            Setting::set('autoPublish', $value);
        }

        $this->sync();

        $this->assertFalse((bool) Product::where('supplier_id', 'S-1')->value('is_published'));
    }

    public static function disabledValues(): array
    {
        return ['0' => ['0'], 'false' => ['false'], 'vide' => [''], 'absent' => [null]];
    }

    public function test_la_resynchronisation_ne_change_pas_la_publication_choisie(): void
    {
        Setting::set('autoPublish', '1');
        $this->sync();
        Product::where('supplier_id', 'S-1')->update(['is_published' => false]); // masqué volontairement

        $this->sync();

        $this->assertFalse((bool) Product::where('supplier_id', 'S-1')->value('is_published'));
    }

    public function test_l_indicateur_booleen_du_reglage(): void
    {
        $this->assertFalse(Setting::flag('autoPublish', 'auto_publish'));
        Setting::set('autoPublish', 'false');
        $this->assertFalse(Setting::flag('autoPublish', 'auto_publish'), "(bool) 'false' ne doit pas valoir vrai");
        Setting::set('autoPublish', '1');
        $this->assertTrue(Setting::flag('autoPublish', 'auto_publish'));
    }
}
