<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ClientAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('account.dashboard');
        }

        return Inertia::render('Auth/Login', [
            'next' => $request->query('next', '/compte'),
        ]);
    }

    public function requestCode(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:191',
        ]);

        $email = strtolower(trim($validated['email']));
        $ip = $request->ip();
        $now = now();

        // 6-digit code
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash = hash_hmac('sha256', "{$email}:{$code}", config('app.key'));

        // Invalidate previous active codes for this email
        DB::table('login_codes')
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => $now]);

        // Insert new code
        DB::table('login_codes')->insert([
            'id' => Str::uuid()->toString(),
            'email' => $email,
            'code_hash' => $codeHash,
            'attempts' => 0,
            'expires_at' => $now->copy()->addMinutes(15),
            'consumed_at' => null,
            'ip' => $ip,
            'created_at' => $now,
        ]);

        // Send email with code
        try {
            app(\App\Services\MailService::class)->sendLoginCode($email, $code);
        } catch (\Throwable $e) {
            \Log::warning("Erreur envoi email code de connexion : " . $e->getMessage());
        }

        return back()->with([
            'step' => 'code',
            'email' => $email,
            'success' => "Un code à 6 chiffres a été envoyé à {$email}.",
        ]);
    }

    public function verifyCode(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:191',
            'code' => 'required|string|size:6',
            'next' => 'nullable|string',
        ]);

        $email = strtolower(trim($validated['email']));
        $code = trim($validated['code']);
        $now = now();

        $codeRow = DB::table('login_codes')
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $now)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$codeRow) {
            return back()->with([
                'step' => 'code',
                'email' => $email,
                'error' => 'Code incorrect ou expiré. Veuillez en demander un nouveau.',
            ]);
        }

        if ($codeRow->attempts >= 5) {
            return back()->with([
                'step' => 'code',
                'email' => $email,
                'error' => 'Nombre d\'essais dépassé. Demandez un nouveau code.',
            ]);
        }

        // Increment attempts
        DB::table('login_codes')
            ->where('id', $codeRow->id)
            ->increment('attempts');

        $expectedHash = hash_hmac('sha256', "{$email}:{$code}", config('app.key'));

        if (!hash_equals($codeRow->code_hash, $expectedHash)) {
            return back()->with([
                'step' => 'code',
                'email' => $email,
                'error' => 'Code incorrect. Vérifiez vos e-mails ou réessayez.',
            ]);
        }

        // Mark code consumed
        DB::table('login_codes')
            ->where('id', $codeRow->id)
            ->update(['consumed_at' => $now]);

        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'id' => Str::uuid()->toString(),
                'role' => 'client',
                'balance_xof' => 0,
                'country' => 'BF',
                'lang' => 'fr',
                'is_blocked' => false,
                'created_at' => $now,
            ]
        );

        if ($user->is_blocked) {
            return back()->with([
                'error' => 'Ce compte est suspendu. Veuillez contacter le support.',
            ]);
        }

        // Connect client
        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        $nextUrl = $validated['next'] ?? '/compte/achats';
        if (!str_starts_with($nextUrl, '/')) {
            $nextUrl = '/compte/achats';
        }

        return redirect($nextUrl)->with('success', 'Connexion réussie !');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Vous avez été déconnecté.');
    }
}
