<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CryptoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthAndSecurityTest extends TestCase
{
    public function test_client_can_request_otp_and_login(): void
    {
        Mail::fake();

        $email = 'client.test@example.com';

        // 1. Demande de code OTP
        $response = $this->post('/connexion/demander-code', [
            'email' => $email,
        ]);

        $response->assertSessionHas('step', 'code');
        $response->assertSessionHas('email', $email);

        // Vérifier l'insertion du code en base
        $codeRow = DB::table('login_codes')
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->first();

        $this->assertNotNull($codeRow);

        // Simuler la saisie d'un code valide
        $rawCode = '123456';
        $hashed = hash_hmac('sha256', "{$email}:{$rawCode}", config('app.key'));
        DB::table('login_codes')->where('id', $codeRow->id)->update(['code_hash' => $hashed]);

        // 2. Vérification OTP erroné
        $failResponse = $this->post('/connexion/verifier-code', [
            'email' => $email,
            'code' => '999999',
        ]);
        $failResponse->assertSessionHas('error');
        $this->assertGuest();

        // 3. Vérification OTP valide
        $successResponse = $this->post('/connexion/verifier-code', [
            'email' => $email,
            'code' => $rawCode,
            'next' => '/compte/achats',
        ]);

        $successResponse->assertRedirect('/compte/achats');
        $this->assertAuthenticated();

        // L'utilisateur doit exister avec le rôle client
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertEquals('client', $user->role);
    }

    public function test_unauthenticated_access_to_admin_returns_stealth_404(): void
    {
        // Un visiteur non admin doit recevoir un 404 (furtif), pas une redirection vers login
        $response = $this->get('/admin');
        $response->assertStatus(404);

        $response2 = $this->get('/admin/commandes');
        $response2->assertStatus(404);
    }

    public function test_admin_login_requires_password_and_2fa_challenge(): void
    {
        Mail::fake();

        $adminEmail = 'admin.test@example.com';
        $password = 'Secret123!';

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Test Admin',
                'role' => 'admin',
                'password' => Hash::make($password),
                'is_blocked' => false,
            ]
        );
        $admin->password = Hash::make($password);
        $admin->role = 'admin';
        $admin->save();

        // 1. Étape 1 : Saisie mot de passe erroné
        $failResponse = $this->post('/admin/connexion', [
            'email' => $adminEmail,
            'password' => 'WrongPassword',
        ]);
        $failResponse->assertSessionHasErrors(['email']);
        $this->assertGuest('admin');

        // 2. Étape 1 : Saisie mot de passe valide -> Pose cookie admin2fa
        $step1Response = $this->post('/admin/connexion', [
            'email' => $adminEmail,
            'password' => $password,
        ]);

        $step1Response->assertRedirect(route('admin.login'));
        $step1Response->assertCookie('admin2fa');

        $cookie = $step1Response->getCookie('admin2fa');
        $this->assertNotNull($cookie);
        [$userId, $token] = explode('.', $cookie->getValue(), 2);
        $this->assertEquals($admin->id, $userId);

        // 3. Étape 2 : Vérification code 2FA
        $key = "admin-2fa:{$admin->id}:" . hash('sha256', $token);
        $codeRow = DB::table('login_codes')->where('email', $key)->first();
        $this->assertNotNull($codeRow);

        $rawCode = '654321';
        $hashed = hash_hmac('sha256', "{$key}:{$rawCode}", config('app.key'));
        DB::table('login_codes')->where('id', $codeRow->id)->update(['code_hash' => $hashed]);

        $verifyResponse = $this->withCookie('admin2fa', $cookie->getValue())
            ->post('/admin/connexion/verifier', [
                'code' => $rawCode,
            ]);

        $verifyResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');

        // Accès admin désormais permis
        $dashboardResponse = $this->actingAs($admin, 'admin')->get('/admin');
        $dashboardResponse->assertStatus(200);
    }

    public function test_reversible_aes_256_gcm_v2_encryption_with_aad(): void
    {
        $orderUuid = Str::uuid()->toString();
        $plainCredentials = "email: user@netflix.com\npass: Secret2026!\npin: 1234";

        $encrypted = CryptoService::encrypt($plainCredentials, $orderUuid);
        $this->assertStringStartsWith('v2.', $encrypted);

        // Déchiffrement avec le bon AAD
        $decrypted = CryptoService::decrypt($encrypted, $orderUuid);
        $this->assertEquals($plainCredentials, $decrypted);

        // Déchiffrement avec un AAD erroné échoue (anti-copie entre commandes)
        $wrongUuid = Str::uuid()->toString();
        $failDecrypted = CryptoService::decrypt($encrypted, $wrongUuid);
        $this->assertNull($failDecrypted);
    }
}
