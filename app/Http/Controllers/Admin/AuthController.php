<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check() && Auth::user()->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, true)) {
            $request->session()->regenerate();

            if (!Auth::user()->hasRole('admin')) {
                Auth::logout();
                return back()->withErrors(['email' => 'Accès refusé. Vous n\'avez pas le rôle administrateur.']);
            }

            return redirect()->intended(route('admin.dashboard'))->with('success', 'Bienvenue dans le backoffice !');
        }

        return back()->withErrors(['email' => 'Identifiants invalides.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'Déconnexion effectuée.');
    }
}
