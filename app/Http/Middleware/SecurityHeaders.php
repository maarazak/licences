<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Ajoute les en-têtes HTTP de sécurité recommandés :
     * - Protection contre le Clickjacking (X-Frame-Options)
     * - Protection contre le reniflage de type MIME (X-Content-Type-Options)
     * - Protection XSS navigateur (X-XSS-Protection)
     * - Politique Referrer stricte (Referrer-Policy)
     * - Désactivation des capteurs sensibles (Permissions-Policy)
     * - En-tête HSTS sur connexions chiffrées (Strict-Transport-Security)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
