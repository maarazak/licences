<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    /**
     * Protège furtivement les routes du backoffice (/admin/*) :
     * Tout visiteur non connecté en tant qu'administrateur reçoit une réponse 404
     * afin de ne pas révéler l'existence ou la structure du backoffice.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        $isAdmin = $admin && ($admin->role === 'admin' || (method_exists($admin, 'hasRole') && $admin->hasRole('admin')));

        if (!$isAdmin || $admin->is_blocked) {
            abort(404);
        }

        return $next($request);
    }
}
