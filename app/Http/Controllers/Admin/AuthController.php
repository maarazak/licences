<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLoginForm(Request $request)
    {
        if (Auth::guard('admin')->check() && $this->isAdmin(Auth::guard('admin')->user())) {
            return redirect()->route('admin.dashboard');
        }

        $step = 'password';
        $email = '';
        $challenge = $this->readChallengeCookie($request);
        if ($challenge) {
            $user = User::find($challenge['user_id']);
            if ($user && $this->isAdmin($user) && !$user->is_blocked) {
                $step = 'code';
                $email = $user->email;
            } else {
                Cookie::queue(Cookie::forget('admin2fa'));
            }
        }

        return view('admin.auth.login', [
            'step' => $step,
            'email' => $email,
        ]);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::where('email', $email)->first();

        if (!$user || !$this->isAdmin($user) || $user->is_blocked || !Hash::check($validated['password'], $user->password)) {
            return back()->withInput(['email' => $email])->withErrors(['email' => 'Identifiants invalides.']);
        }

        $token = Str::random(40);
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $key = "admin-2fa:{$user->id}:" . hash('sha256', $token);
        $codeHash = hash_hmac('sha256', "{$key}:{$code}", config('app.key'));
        $now = now();

        DB::table('login_codes')
            ->where('email', 'like', "admin-2fa:{$user->id}:%")
            ->whereNull('consumed_at')
            ->update(['consumed_at' => $now]);

        DB::table('login_codes')->insert([
            'id' => Str::uuid()->toString(),
            'email' => $key,
            'code_hash' => $codeHash,
            'attempts' => 0,
            'expires_at' => $now->copy()->addMinutes(10),
            'consumed_at' => null,
            'ip' => $request->ip(),
            'created_at' => $now,
        ]);

        try {
            app(MailService::class)->sendAdmin2faCode($user->email, $code);
        } catch (\Throwable $e) {
            \Log::warning("Erreur envoi 2FA admin : " . $e->getMessage());
        }

        $cookie = cookie('admin2fa', "{$user->id}.{$token}", 10, '/', null, $request->secure(), true, false, 'strict');

        return redirect()->route('admin.login')->withCookie($cookie);
    }

    public function verify2fa(Request $request)
    {
        $challenge = $this->readChallengeCookie($request);
        if (!$challenge) {
            return redirect()->route('admin.login')->withErrors(['email' => "Vérification expirée ou trop d'essais. Reconnectez-vous."]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $code = trim($validated['code']);
        $key = "admin-2fa:{$challenge['user_id']}:" . hash('sha256', $challenge['token']);
        $now = now();

        $codeRow = DB::table('login_codes')
            ->where('email', $key)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $now)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$codeRow) {
            Cookie::queue(Cookie::forget('admin2fa'));
            return redirect()->route('admin.login')->withErrors(['email' => "Vérification expirée ou session invalide. Reconnectez-vous."]);
        }

        if ($codeRow->attempts >= 5) {
            Cookie::queue(Cookie::forget('admin2fa'));
            return redirect()->route('admin.login')->withErrors(['email' => "Nombre d'essais dépassé. Reconnectez-vous pour obtenir un nouveau code."]);
        }

        DB::table('login_codes')->where('id', $codeRow->id)->increment('attempts');

        $expectedHash = hash_hmac('sha256', "{$key}:{$code}", config('app.key'));
        if (!hash_equals($codeRow->code_hash, $expectedHash)) {
            return back()->withErrors(['code' => 'Code de vérification incorrect.']);
        }

        DB::table('login_codes')->where('id', $codeRow->id)->update(['consumed_at' => $now]);

        $user = User::find($challenge['user_id']);
        if (!$user || !$this->isAdmin($user) || $user->is_blocked) {
            Cookie::queue(Cookie::forget('admin2fa'));
            return redirect()->route('admin.login')->withErrors(['email' => 'Accès non autorisé.']);
        }

        Auth::guard('admin')->login($user);
        $request->session()->regenerate();
        $user->update(['last_login_at' => $now]);

        return redirect()->intended(route('admin.dashboard'))
            ->withCookie(Cookie::forget('admin2fa'))
            ->with('success', 'Bienvenue dans le backoffice !');
    }

    public function cancel2fa()
    {
        return redirect()->route('admin.login')->withCookie(Cookie::forget('admin2fa'));
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->withCookie(Cookie::forget('admin2fa'))->with('success', 'Déconnexion effectuée.');
    }

    protected function isAdmin(?User $user): bool
    {
        if (!$user) return false;
        return $user->role === 'admin' || (method_exists($user, 'hasRole') && $user->hasRole('admin'));
    }

    protected function readChallengeCookie(Request $request): ?array
    {
        $val = $request->cookie('admin2fa');
        if (!$val || !str_contains($val, '.')) {
            return null;
        }

        [$userId, $token] = explode('.', $val, 2);
        if (empty($userId) || empty($token)) {
            return null;
        }

        return ['user_id' => $userId, 'token' => $token];
    }
}
