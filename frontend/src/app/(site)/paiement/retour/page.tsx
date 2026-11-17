import { CheckCircle2, Clock, TriangleAlert, Wallet, XCircle } from "lucide-react";
import type { Metadata } from "next";
import { headers } from "next/headers";
import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { AutoRefresh } from "@/components/auto-refresh";
import { OrderStatusBadge } from "@/components/status-badges";
import { formatXof } from "@/lib/format";
import { requireUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";
import { findOrderByPayment, getUserOrder, syncPaymentThrottled } from "@/server/services/orders";
import { findWalletDepositByPayment, getUserWalletDeposit, syncWalletDepositThrottled } from "@/server/services/wallet";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Suivi du paiement"), robots: { index: false } };
}

const MESSAGES: Record<string, { title: string; text: string; tone: "wait" | "ok" | "warn" | "ko" }> = {
  pending_payment: { title: "Confirmation du paiement en attente", text: "Nous attendons la confirmation de votre opérateur. Cette page se met à jour toute seule : inutile de payer à nouveau.", tone: "wait" },
  paid: { title: "Paiement reçu", text: "Nous préparons vos accès.", tone: "ok" },
  processing: { title: "Paiement reçu", text: "Nous préparons vos accès. Cela prend en général moins d'une minute.", tone: "ok" },
  activation_awaiting: { title: "Paiement reçu, activation en cours", text: "Cette offre est activée manuellement. Vous recevrez un e-mail dès que c'est fait.", tone: "ok" },
  delivered: { title: "Vos accès sont disponibles", text: "Retrouvez-les à tout moment dans Mes achats.", tone: "ok" },
  failed: { title: "Paiement confirmé, livraison en cours", text: "Votre paiement a été reçu avec succès. La préparation de votre licence est prise en charge par notre équipe.", tone: "ok" },
  payment_failed: { title: "Le paiement n'a pas abouti", text: "Aucune commande n'a été validée. Vérifiez votre solde avant de réessayer.", tone: "ko" },
  expired: { title: "La session de paiement a expiré", text: "Aucun montant n'a été débité. Vous pouvez recommencer la commande.", tone: "ko" },
  cancelled: { title: "Commande annulée", text: "Aucun montant n'a été débité.", tone: "ko" },
  refunded: { title: "Commande remboursée", text: "Le montant vous a été restitué.", tone: "ok" },
};

const TONES = {
  wait: { Icon: Clock, box: "bg-info-soft text-info" },
  ok: { Icon: CheckCircle2, box: "bg-success-soft text-success" },
  warn: { Icon: TriangleAlert, box: "bg-warning-soft text-warning" },
  ko: { Icon: XCircle, box: "bg-danger-soft text-danger" },
} as const;

/**
 * Page de retour du prestataire. Revenir ici ne prouve rien : l'état est relu
 * auprès de pawaPay côté serveur avant d'être affiché.
 */
export default async function PaymentReturnPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const params = await searchParams;
  let orderId = Array.isArray(params.commande) ? (params.commande[0] ?? "") : (params.commande ?? "");
  const depositId = Array.isArray(params.depositId) ? (params.depositId[0] ?? "") : (params.depositId ?? "");
  const rawDepotId = Array.isArray(params.depot) ? (params.depot[0] ?? "") : (params.depot ?? "");

  if (!orderId && depositId) {
    const matched = await findOrderByPayment({ checkoutId: depositId, paymentId: depositId });
    if (matched) orderId = matched.id;
  }

  const h = await headers();
  const host = h.get("host") ?? "";
  if (host.startsWith("127.0.0.1")) {
    const targetHost = host.replace("127.0.0.1", "localhost");
    const query = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
      if (typeof v === "string") query.set(k, v);
      else if (Array.isArray(v) && v[0]) query.set(k, v[0]);
    }
    const qStr = query.toString();
    redirect(`http://${targetHost}/paiement/retour${qStr ? `?${qStr}` : ""}`);
  }

  // Cas 1 : Recharge de solde (Dépôt Mobile Money)
  const walletDeposit =
    (rawDepotId && (await findWalletDepositByPayment({ checkoutId: rawDepotId, paymentId: rawDepotId }))) ||
    (!orderId && depositId ? await findWalletDepositByPayment({ checkoutId: depositId, paymentId: depositId }) : null);

  if (walletDeposit) {
    const user = await requireUser(`/paiement/retour?depot=${encodeURIComponent(walletDeposit.id)}`);
    const { t } = await getI18n();

    let deposit = walletDeposit;
    if (deposit.status === "pending") {
      await syncWalletDepositThrottled(deposit.id);
      deposit = (await getUserWalletDeposit(user.id, deposit.id)) ?? deposit;
    }

    const isDone = deposit.status === "completed";
    const isPending = deposit.status === "pending";

    return (
      <div className="container-page py-12">
        {isPending ? <AutoRefresh seconds={4} /> : null}
        <div className="card mx-auto max-w-lg p-6 shadow-pop sm:p-8">
          <span
            className={`flex size-14 items-center justify-center rounded-2xl ${
              isDone
                ? "bg-success-soft text-success"
                : isPending
                  ? "bg-info-soft text-info"
                  : "bg-danger-soft text-danger"
            }`}
          >
            {isDone ? (
              <CheckCircle2 className="size-7" aria-hidden="true" />
            ) : isPending ? (
              <Clock className="size-7" aria-hidden="true" />
            ) : (
              <XCircle className="size-7" aria-hidden="true" />
            )}
          </span>
          <h1 className="h2 mt-4">
            {isDone
              ? t("Recharge réussie !")
              : isPending
                ? t("Confirmation de votre recharge en attente")
                : t("La recharge n'a pas abouti")}
          </h1>
          <p className="mt-2 text-sm leading-relaxed text-muted">
            {isDone
              ? t("{amount} ont été ajoutés avec succès à votre solde. Vous pouvez l'utiliser pour régler vos achats.", {
                  amount: formatXof(deposit.amountXof),
                })
              : isPending
                ? t("Nous attendons la validation de votre opérateur Mobile Money. Cette page se met à jour automatiquement.")
                : t("Le paiement a été refusé ou annulé. Aucun montant n'a été prélevé.")}
          </p>
          <p className="mt-5 rounded-xl bg-canvas px-4 py-3 text-sm">
            <span className="font-semibold">{t("Recharge de solde")}</span> — {formatXof(deposit.amountXof)}
            <span className="block text-xs text-muted">
              {t("Référence")} {deposit.reference}
            </span>
          </p>
          <div className="mt-6 flex flex-wrap gap-3">
            <Link href="/compte/solde" className="btn btn-brand">
              <Wallet className="size-4" aria-hidden="true" />
              {t("Voir mon solde")}
            </Link>
            {isDone ? (
              <Link href="/boutique" className="btn btn-soft">
                {t("Aller à la boutique")}
              </Link>
            ) : null}
            {isPending ? (
              <Link href={`/paiement/reprendre?depot=${deposit.id}`} className="btn btn-soft">
                {t("Reprendre le paiement")}
              </Link>
            ) : null}
            {!isDone && !isPending ? (
              <Link href="/compte/solde" className="btn btn-soft">
                {t("Réessayer")}
              </Link>
            ) : null}
          </div>
        </div>
      </div>
    );
  }

  const user = await requireUser(`/paiement/retour?commande=${encodeURIComponent(orderId)}`);
  const { t } = await getI18n();

  let order = await getUserOrder(user.id, orderId);
  if (!order) notFound();
  if (order.status === "pending_payment") {
    await syncPaymentThrottled(order.id);
    order = (await getUserOrder(user.id, orderId)) ?? order;
  }

  const message = MESSAGES[order.status];
  const { Icon, box } = TONES[message.tone];
  const inProgress = ["pending_payment", "paid", "processing"].includes(order.status);

  return (
    <div className="container-page py-12">
      {inProgress ? <AutoRefresh seconds={5} /> : null}
      <div className="card mx-auto max-w-lg p-6 shadow-pop sm:p-8">
        <span className={`flex size-14 items-center justify-center rounded-2xl ${box}`}>
          <Icon className="size-7" aria-hidden="true" />
        </span>
        <div className="mt-4">
          <OrderStatusBadge status={order.status} />
        </div>
        <h1 className="h2 mt-3">{t(message.title)}</h1>
        <p className="mt-2 text-sm leading-relaxed text-muted">{t(message.text)}</p>
        <p className="mt-5 rounded-xl bg-canvas px-4 py-3 text-sm">
          <span className="font-semibold">{order.productName}</span> × {order.quantity} — {formatXof(order.totalXof)}
          <span className="block text-xs text-muted">
            {t("Commande")} {order.reference}
          </span>
        </p>
        <div className="mt-6 flex flex-wrap gap-3">
          <Link href={`/compte/achats/${order.id}`} className="btn btn-brand">
            {t("Voir la commande")}
          </Link>
          {order.status === "pending_payment" ? (
            <Link href={`/paiement/reprendre?commande=${order.id}`} className="btn btn-soft">
              {t("Reprendre le paiement")}
            </Link>
          ) : null}
          {["payment_failed", "expired", "cancelled"].includes(order.status) ? (
            <Link href="/boutique" className="btn btn-soft">
              {t("Retour à la boutique")}
            </Link>
          ) : null}
        </div>
      </div>
    </div>
  );
}
