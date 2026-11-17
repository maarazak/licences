import { NextResponse } from "next/server";
import { rateLimit } from "@/server/security/rate-limit";
import { clientIp } from "@/server/security/request";
import { findOrderByPayment, syncPaymentThrottled } from "@/server/services/orders";
import { findWalletDepositByPayment, syncWalletDepositThrottled } from "@/server/services/wallet";

export const dynamic = "force-dynamic";

const MAX_BODY_BYTES = 64 * 1024;
// depositId est un UUID v4 (36 caractères hexadécimaux et tirets)
const UUID_OR_ID = /^[A-Za-z0-9_-]{4,191}$/;

/**
 * Webhook de notification de paiement pawaPay (Deposit Callback).
 * URL à déclarer sur le dashboard pawaPay : https://<votre-domaine>/api/webhooks/pawapay
 *
 * Règle de sécurité non négociable :
 * Le contenu reçu n'est JAMAIS cru sur parole. On n'en extrait que l'identifiant
 * de la transaction (depositId), puis notre serveur relit l'état authentique
 * directement auprès de l'API pawaPay avec notre clé secrète (`syncPayment`).
 * Un attaquant qui envoie un faux webhook ne peut donc en aucun cas valider
 * un paiement frauduleux : seule la confirmation réelle chez pawaPay déclenche
 * le passage à « payé » et la livraison des accès numériques.
 */
export async function POST(request: Request): Promise<NextResponse> {
  const limit = await rateLimit("webhook-pawapay", await clientIp(), 240, 60);
  if (!limit.ok) {
    return NextResponse.json(
      { error: "rate_limited" },
      { status: 429, headers: { "retry-after": String(limit.retryAfterSeconds) } },
    );
  }

  // Contrôle de taille AVANT de lire le flux puis pendant la lecture en streaming.
  if (Number(request.headers.get("content-length") ?? 0) > MAX_BODY_BYTES) {
    return NextResponse.json({ error: "payload_too_large" }, { status: 413 });
  }
  const raw = await readLimited(request, MAX_BODY_BYTES);
  if (raw === null) {
    return NextResponse.json({ error: "payload_too_large" }, { status: 413 });
  }

  let body: unknown = null;
  try {
    body = JSON.parse(raw);
  } catch {
    return NextResponse.json({ error: "invalid_json" }, { status: 400 });
  }

  const payload = body && typeof body === "object" ? (body as Record<string, unknown>) : {};
  const data = payload.data && typeof payload.data === "object" ? (payload.data as Record<string, unknown>) : payload;

  const rawDepositId =
    data.depositId ??
    payload.depositId ??
    data.checkoutId ??
    payload.checkoutId ??
    request.headers.get("x-deposit-id");
  const depositId = typeof rawDepositId === "string" && UUID_OR_ID.test(rawDepositId) ? rawDepositId : null;

  if (!depositId) {
    // Si la requête ne contient pas d'identifiant valide, on accuse réception sans action.
    return NextResponse.json({ received: true });
  }

  try {
    const order = await findOrderByPayment({ checkoutId: depositId, paymentId: depositId });
    if (order) {
      // Relecture protégée (au plus 6 par minute et par commande) pour préserver le quota d'API pawaPay.
      await syncPaymentThrottled(order.id);
    } else {
      const walletDeposit = await findWalletDepositByPayment({ checkoutId: depositId, paymentId: depositId });
      if (walletDeposit) {
        await syncWalletDepositThrottled(walletDeposit.id);
      }
    }
  } catch (error) {
    console.error("[webhook pawapay] traitement en échec", error);
    // Retourne 500 pour que pawaPay réessaie sa notification plus tard.
    return NextResponse.json({ error: "processing_failed" }, { status: 500 });
  }

  return NextResponse.json({ received: true });
}

/** Lit le corps en s'arrêtant au-delà de `max` octets (null = trop gros). */
async function readLimited(request: Request, max: number): Promise<string | null> {
  const reader = request.body?.getReader();
  if (!reader) return "";
  const decoder = new TextDecoder();
  let total = 0;
  let out = "";
  for (;;) {
    const { done, value } = await reader.read();
    if (done) break;
    total += value.byteLength;
    if (total > max) {
      await reader.cancel();
      return null;
    }
    out += decoder.decode(value, { stream: true });
  }
  return out + decoder.decode();
}
