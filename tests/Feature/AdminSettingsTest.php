<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'email' => 'admin-settings@licences.pro',
            'role' => 'admin',
        ]);
    }

    public function test_guest_cannot_access_admin_settings(): void
    {
        $response = $this->get('/admin/parametres');
        $response->assertStatus(404);
    }

    public function test_admin_can_view_settings_page(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/parametres');

        $response->assertStatus(200);
        $response->assertSee('Paramètres');
        $response->assertSee('Taux du dollar : 1 $ en FCFA');
        $response->assertSee('Marge générale (%)');
        $response->assertSee('Marge minimale par vente (FCFA)');
        $response->assertSee('Arrondi (FCFA)');
        $response->assertSee('Publier automatiquement les nouveaux produits');
        $response->assertSee('Configuration du serveur');
    }

    public function test_admin_can_update_settings(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/parametres', [
            'usdRate' => 675,
            'marginPercent' => 120,
            'minMarginXof' => 1500,
            'roundingStep' => 500,
            'autoPublish' => '1',
            'notice' => '⚡ Promotion de Noël en cours',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('675', Setting::get('usdRate'));
        $this->assertEquals('675', Setting::get('usd_rate'));
        $this->assertEquals('120', Setting::get('marginPercent'));
        $this->assertEquals('120', Setting::get('default_margin_percent'));
        $this->assertEquals('1500', Setting::get('minMarginXof'));
        $this->assertEquals('500', Setting::get('roundingStep'));
        $this->assertEquals('1', Setting::get('autoPublish'));
        $this->assertEquals('⚡ Promotion de Noël en cours', Setting::get('notice'));
    }
}
