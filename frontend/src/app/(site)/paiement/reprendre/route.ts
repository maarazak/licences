import { redirect } from "next/navigation";
import { currentUser } from "@/server/auth/session";
import { getFreshOrderPaymentUrl } from "@/server/services/orders";
import { getFreshWalletDepositUrl } from "@/server/services/wallet";

export const dynamic = "force-dynamic";

/**
 * Route sécurisée de reprise de paiement :
 * - Empêche l'erreur AWS CloudFront « AccessDenied » causée par l'expiration
 *   de la signature après 5 minutes.
 * - Si le lien est encore dans sa fenêtre de validité, redirige immédiatement.
 * - Si le lien a expiré, régénère automatiquement une session pawaPay active
 *   avec une nouvelle signature CloudFront, met à jour la commande/dépôt en base,
 *   et redirige le client de façon totalement transparente.
 */
export async function GET(request: Request): Promise<never> {
  const { searchParams } = new URL(request.url);
  const orderId = searchParams.get("commande");
  const depotId = searchParams.get("depot");

  const user = await currentUser();
  if (!user) {
    redirect(`/connexion?next=${encodeURIComponent(request.url)}`);
  }

  if (orderId) {
    const paymentUrl = await getFreshOrderPaymentUrl(orderId, user);
    if (paymentUrl) {
      redirect(paymentUrl);
    }
    redirect(`/compte/achats/${orderId}`);
  }

  if (depotId) {
    const paymentUrl = await getFreshWalletDepositUrl(depotId, user);
    if (paymentUrl) {
      redirect(paymentUrl);
    }
    redirect("/compte/solde");
  }

  redirect("/boutique");
}
