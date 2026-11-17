<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_change_language(): void
    {
        $response = $this->post('/locale/lang', ['lang' => 'en']);
        $response->assertSessionHasNoErrors();
        $response->assertCookie('lang', 'en');
    }

    public function test_authenticated_user_language_change_persists_to_db(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'email' => 'user-lang@test.ci',
            'role' => 'client',
            'lang' => 'fr',
        ]);

        $response = $this->actingAs($user)
            ->post('/locale/lang', ['lang' => 'en']);

        $response->assertCookie('lang', 'en');
        $this->assertEquals('en', $user->fresh()->lang);
    }

    public function test_guest_can_change_currency(): void
    {
        $response = $this->post('/locale/currency', ['currency' => 'USD']);
        $response->assertSessionHasNoErrors();
        $response->assertCookie('currency', 'USD');
    }

    public function test_inertia_shares_locale_props(): void
    {
        $response = $this->withCookies([
            'lang' => 'en',
            'currency' => 'USD',
        ])->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('locale')
            ->where('locale.lang', 'en')
            ->where('locale.currency', 'USD')
        );
    }
}
