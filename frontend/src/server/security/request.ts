import "server-only";
import { headers } from "next/headers";
import { env } from "@/server/env";

/**
 * Adresse IP du client. La première valeur de X-Forwarded-For est choisie par le client : on ne s'y fie pas.
 * `x-real-ip` n'est PAS cru par défaut (falsifiable) : il faut le déclarer via TRUSTED_IP_HEADER.
 */
export async function clientIp(): Promise<string> {
  const h = await headers();
  // En-tête de confiance déclaré par l'exploitant (TRUSTED_IP_HEADER, ex. « x-real-ip » ou « x-vercel-forwarded-for »),
  // à utiliser uniquement si le reverse proxy l'écrase systématiquement. Sinon n'importe quel client le falsifierait.
  const trusted = env().TRUSTED_IP_HEADER.trim().toLowerCase();
  if (trusted) {
    const value = h.get(trusted)?.split(",").at(-1)?.trim();
    if (value) return value.slice(0, 64);
  }
  const forwarded = h.get("x-forwarded-for")?.split(",").map((part) => part.trim()).filter(Boolean);
  // Dernière entrée = ajoutée par le proxy le plus proche ; TRUSTED_PROXY_HOPS remonte derrière des proxys de confiance.
  const hops = env().TRUSTED_PROXY_HOPS;
  return (forwarded?.at(-1 - hops) ?? forwarded?.[0] ?? "inconnue").slice(0, 64);
}

export async function userAgent(): Promise<string> {
  const h = await headers();
  return (h.get("user-agent") ?? "").slice(0, 255);
}

/**
 * Chemin de redirection interne sûr : commence par un seul « / », sans schéma ni hôte.
 * Empêche les redirections ouvertes via le paramètre `next`.
 */
export function safeNextPath(value: unknown, fallback = "/compte/achats"): string {
  if (typeof value !== "string") return fallback;
  if (!value.startsWith("/") || value.startsWith("//") || value.includes("\\") || value.length > 500) return fallback;
  if (/[\u0000-\u001f]/.test(value)) return fallback;
  return value;
}
