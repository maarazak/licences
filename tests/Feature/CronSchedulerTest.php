<?php

namespace Tests\Feature;

use App\Models\LoginCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\RateLimit;
use App\Models\Setting;
use App\Models\StockAlert;
use App\Models\User;
use App\Services\SchedulerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CronSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake([
            '*/catalog' => Http::response(['products' => []], 200),
            '*/balance' => Http::response(['balance' => 100.00], 200),
            '*/deposits/*' => Http::response(['status' => 'COMPLETED'], 200),
            '*' => Http::response([], 200),
        ]);
    }

    public function test_api_cron_tick_requires_valid_bearer_token(): void
    {
        config(['services.cron_secret' => 'test-secret-key-12345']);

        // Sans token
        $response = $this->getJson('/api/cron/tick');
        $response->assertStatus(401);
        $response->assertJson(['error' => 'unauthorized']);

        // Token invalide
        $response = $this->getJson('/api/cron/tick', [
            'Authorization' => 'Bearer wrong-secret',
        ]);
        $response->assertStatus(401);
        $response->assertJson(['error' => 'unauthorized']);

        // Token valide (GET ou POST)
        $response = $this->postJson('/api/cron/tick', [], [
            'Authorization' => 'Bearer test-secret-key-12345',
        ]);
        $response->assertStatus(200);
        $this->assertArrayHasKey('orders', $response->json());
        $this->assertArrayHasKey('purge', $response->json());
    }

    public function test_cron_lock_prevents_simultaneous_execution(): void
    {
        // Poser un verrou actif
        $lock = Cache::lock('cron_tick_lock', 55);
        $lock->get();

        $scheduler = app(SchedulerService::class);
        $report = $scheduler->runTick();

        $this->assertArrayHasKey('skipped', $report);
        $this->assertStringContainsString('en cours', $report['skipped']);

        $lock->release();
    }

    public function test_cron_tick_purges_expired_data(): void
    {
        // Créer un code de connexion expiré et un valide
        LoginCode::create([
            'id' => (string) Str::uuid(),
            'email' => 'expired@test.ci',
            'code_hash' => hash('sha256', '123456'),
            'expires_at' => now()->subMinutes(15),
            'attempts' => 0,
        ]);
        LoginCode::create([
            'id' => (string) Str::uuid(),
            'email' => 'valid@test.ci',
            'code_hash' => hash('sha256', '654321'),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        // Créer un rate limit expiré et un actif
        RateLimit::create([
            'key' => 'test:rate:expired',
            'points' => 5,
            'reset_at' => now()->subMinute(),
        ]);
        RateLimit::create([
            'key' => 'test:rate:valid',
            'points' => 2,
            'reset_at' => now()->addMinute(),
        ]);

        $scheduler = app(SchedulerService::class);
        $report = $scheduler->runTick();

        $this->assertDatabaseMissing('login_codes', ['email' => 'expired@test.ci']);
        $this->assertDatabaseHas('login_codes', ['email' => 'valid@test.ci']);

        $this->assertDatabaseMissing('rate_limits', ['key' => 'test:rate:expired']);
        $this->assertDatabaseHas('rate_limits', ['key' => 'test:rate:valid']);

        $this->assertArrayHasKey('purge', $report);
        $this->assertEquals(1, $report['purge']['login_codes']);
        $this->assertEquals(1, $report['purge']['rate_limits']);
    }

    public function test_cron_tick_respects_catalog_sync_gap(): void
    {
        // Récemment synchronisé (il y a 10 secondes)
        Setting::set('lastCatalogSyncAt', now()->subSeconds(10)->toIso8601String());

        $scheduler = app(SchedulerService::class);
        $report = $scheduler->runTick();

        $this->assertEquals('à jour (< 45s)', $report['catalog']);
    }

    public function test_artisan_cron_tick_command(): void
    {
        $this->artisan('cron:tick')
            ->expectsOutputToContain('Exécution de la tâche planifiée cron:tick...')
            ->expectsOutputToContain('Tâche terminée.')
            ->assertExitCode(0);
    }
}
