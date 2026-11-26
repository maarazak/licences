<?php

namespace App\Support;

use App\Services\CinetPayService;
use Illuminate\Http\Request;

/**
 * Pays probable du visiteur, d'après l'en-tête posé par l'hébergeur ou le CDN (déduit de l'adresse IP côté infrastructure) :
 * Cloudflare (CF-IPCountry), CloudFront, Vercel, etc. Aucun service tiers n'est appelé et aucune donnée n'est envoyée.
 * Sert uniquement à PRÉSÉLECTIONNER le pays du formulaire : le client peut toujours le changer, et un en-tête falsifié
 * ne peut rien d'autre que présélectionner un pays de la liste autorisée.
 * En local ou sans CDN, aucun en-tête n'existe : la méthode renvoie null.
 */
class CountryDetector
{
    private const HEADERS = ['CF-IPCountry', 'CloudFront-Viewer-Country', 'X-Vercel-IP-Country', 'X-Country-Code', 'X-AppEngine-Country'];

    /** Code pays ISO (2 lettres) pris en charge pour le paiement en FCFA, ou null. */
    public static function fromRequest(Request $request): ?string
    {
        foreach (self::HEADERS as $header) {
            $value = strtoupper(trim((string) $request->header($header, '')));
            if (preg_match('/^[A-Z]{2}$/', $value) === 1 && in_array($value, CinetPayService::PAYABLE_COUNTRIES, true)) {
                return $value;
            }
        }

        return null;
    }

    /** Pays à présélectionner : détecté, sinon celui du compte (s'il est payable), sinon le Burkina Faso. */
    public static function preselect(Request $request, ?string $savedCountry): string
    {
        $saved = strtoupper((string) $savedCountry);

        return self::fromRequest($request)
            ?? (in_array($saved, CinetPayService::PAYABLE_COUNTRIES, true) ? $saved : 'BF');
    }
}
