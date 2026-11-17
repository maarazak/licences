import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { DeliveryList } from "@/components/delivery-list";
import { ActionButton, ActionForm, SubmitButton } from "@/components/forms";
import { Details } from "@/components/ui";
import { OrderStatusBadge } from "@/components/status-badges";
import { COUNTRIES } from "@/lib/enums";
import { formatDateTime, formatUsdCents, formatXof } from "@/lib/format";
import { costXof } from "@/lib/pricing";
import { creditOrderAction, markFailedAction, noteOrderAction, recheckPaymentAction, refundOrderAction, retryOrderAction } from "@/server/actions/admin";
import { requireAdmin } from "@/server/auth/session";
import { audit } from "@/server/services/audit";
import { adminGetOrder, readDelivery } from "@/server/services/orders";

export const metadata: Metadata = { title: "Commande" };

export default async function AdminOrderPage({ params, searchParams }: { params: Promise<{ id: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const { id } = await params;
  const admin = await requireAdmin(`/admin/commandes/${id}`);
  const found = /^[0-9a-f-]{36}$/.test(id) ? await adminGetOrder(id) : null;
  if (!found) notFound();
  const { order, customer, events } = found;

  // Les accès ne s'affichent que sur demande explicite, et chaque consultation est journalisée.
  const reveal = (await searchParams).acces === "1" && order.status === "delivered";
  const items = reveal ? readDelivery(order) : [];
  if (reveal) await audit(admin, "commande.voir-acces", order.id, order.reference);

  const cost = costXof(order.unitCostUsdCents * order.quantity, order.usdRate);
  const country = COUNTRIES.find((c) => c.code === order.customerCountry)?.name ?? "—";

  return (
    <div className="space-y-6">
      <nav aria-label="Fil d'Ariane" className="text-xs text-muted">
        <Link href="/admin/commandes" className="hover:text-ink">
          Commandes
        </Link>{" "}
        / <span className="text-ink">{order.reference}</span>
      </nav>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="h1">{order.reference}</h1>
        <OrderStatusBadge status={order.status} admin />
      </div>

      {order.status === "failed" ? (
        <section className="card space-y-4 border-l-4 border-l-accent p-5">
          <div>
            <h2 className="h3">Commande payée non livrée</h2>
            <p className="mt-1 text-sm text-muted">
              Cause : <strong>{order.failureCode ?? "inconnue"}</strong> — {order.failureMessage ?? "sans détail"}
            </p>
            {order.refundRequestedAt ? <p className="mt-2 text-sm font-semibold text-danger">Le client a demandé un remboursement le {formatDateTime(order.refundRequestedAt)}.</p> : null}
          </div>
          {["too_many_attempts", "empty_delivery", "uncertain_outcome", "manual_hold", "activation_timeout"].includes(order.failureCode ?? "") ? (
            <p className="alert alert-warning">Issue incertaine chez le fournisseur : vérifiez cette commande dans le bot Telegram avant toute action. « Relancer » réutilise la même clé d&apos;idempotence (pas de double achat, possible pendant 23 h) ; « Créditer » ne se fait que si rien n&apos;a été livré.</p>
          ) : null}
          <div className="flex flex-wrap items-start gap-3">
            {order.failureCode !== "amount_mismatch" ? (
              <>
                <ActionButton action={retryOrderAction} fields={{ orderId: order.id }} className="btn btn-brand btn-sm" busy="Achat chez le fournisseur en cours…" confirm="Relancer l'achat chez le fournisseur ? Le solde fournisseur sera débité.">
                  Relancer la livraison
                </ActionButton>
                <ActionButton action={creditOrderAction} fields={{ orderId: order.id }} danger busy="Crédit du solde en cours…" confirm={`Créditer ${order.totalXof} FCFA sur le solde du client ? Cette action clôt la commande.${order.refundRequestedAt ? " ATTENTION : le client a demandé un remboursement chez pawaPay ; ne créditez que s'il ne l'a pas reçu." : ""}`}>
                  Créditer le solde du client
                </ActionButton>
              </>
            ) : null}
          </div>
          {order.paidWith === "gateway" ? (
            <ActionForm action={refundOrderAction} className="space-y-3 border-t border-line pt-4">
              <input type="hidden" name="orderId" value={order.id} />
              <p className="text-sm font-semibold">Remboursement chez le prestataire</p>
              <p className="text-xs leading-relaxed text-muted">Émettez d&apos;abord le remboursement dans le tableau de bord pawaPay (paiement {order.paymentIntentId ?? "—"}), puis enregistrez-le ici. Cette étape ne déclenche aucun mouvement d&apos;argent.</p>
              <label className="flex cursor-pointer items-center gap-2 text-sm">
                <input type="checkbox" name="confirm" className="size-4 accent-brand" />
                J&apos;ai émis le remboursement de {formatXof(order.totalXof)} chez pawaPay
              </label>
              <SubmitButton className="btn btn-danger btn-sm">Marquer comme remboursée</SubmitButton>
            </ActionForm>
          ) : null}
        </section>
      ) : null}

      {["pending_payment", "expired", "payment_failed"].includes(order.status) ? (
        <ActionButton action={recheckPaymentAction} fields={{ orderId: order.id }} busy="Vérification du paiement auprès de pawaPay…">
          Revérifier le paiement auprès de pawaPay
        </ActionButton>
      ) : null}

      {order.status === "processing" || order.status === "activation_awaiting" ? (
        <section className="card space-y-3 border-l-4 border-l-accent p-5">
          <h2 className="h3">Commande bloquée ?</h2>
          <p className="text-sm text-muted">Si le fournisseur ne termine jamais cette commande, mettez-la en échec pour pouvoir la créditer, la relancer ou la rembourser. Vérifiez d&apos;abord son état dans le bot Telegram.</p>
          <ActionButton action={markFailedAction} fields={{ orderId: order.id }} danger confirm="Mettre cette commande en échec ?">
            Mettre en échec
          </ActionButton>
        </section>
      ) : null}

      <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
        <section className="card px-5 py-2">
          <h2 className="h3 pt-3">Commande</h2>
          <Details
            rows={[
              ["Produit", `${order.productName} × ${order.quantity}`],
              ["Montant payé", formatXof(order.totalXof)],
              ["Coût fournisseur", `${formatUsdCents(order.unitCostUsdCents * order.quantity)} ≈ ${formatXof(cost)}`],
              ["Marge", formatXof(order.totalXof - cost)],
              ["Taux appliqué", `1 $ = ${order.usdRate} FCFA`],
              ["Payée avec", order.paidWith === "balance" ? "Solde client" : "pawaPay"],
              ["Créée le", formatDateTime(order.createdAt)],
              ["Payée le", formatDateTime(order.paidAt)],
              ["Livrée le", formatDateTime(order.deliveredAt)],
              ...(order.refundedAt ? ([["Remboursée le", `${formatDateTime(order.refundedAt)} (${order.refundMethod === "balance" ? "avoir" : "prestataire"})`]] as Array<[string, string]>) : []),
            ]}
          />
        </section>
        <section className="card px-5 py-2">
          <h2 className="h3 pt-3">Client et références</h2>
          <Details
            rows={[
              ["E-mail", customer?.email ?? "—"],
              ["Nom", `${order.customerFirstName} ${order.customerLastName}`],
              ["Téléphone", order.customerPhone ?? "—"],
              ["Pays", country],
              ...(order.activationEmails?.length ? ([["E-mails à activer", order.activationEmails.join(", ")]] as Array<[string, string]>) : []),
              ["Paiement pawaPay", <span key="p" className="font-mono text-xs">{order.paymentIntentId ?? "—"}</span>],
              ["Commande fournisseur", <span key="s" className="font-mono text-xs">{order.supplierOrderId ?? "—"}</span>],
              ["Statut fournisseur", order.supplierStatus ?? "—"],
              ["Essais de livraison", String(order.fulfilAttempts)],
              ["Relances de paiement", order.reminderCount ? `${order.reminderCount} (dernière : ${formatDateTime(order.lastReminderAt)})` : "Aucune"],
            ]}
          />
        </section>
      </div>

      {order.status === "delivered" ? (
        <section className="card p-5">
          <h2 className="h3">Accès livrés</h2>
          {reveal ? (
            <div className="mt-3">{items.length ? <DeliveryList items={items} /> : <p className="alert alert-error">Déchiffrement impossible : vérifiez ENCRYPTION_KEY.</p>}</div>
          ) : (
            <>
              <p className="hint mb-3">Masqués par défaut. Chaque consultation est inscrite au journal.</p>
              <Link href={`/admin/commandes/${order.id}?acces=1`} className="btn btn-soft btn-sm">
                Afficher les accès
              </Link>
            </>
          )}
        </section>
      ) : null}

      <ActionForm action={noteOrderAction} className="card space-y-3 p-5">
        <input type="hidden" name="orderId" value={order.id} />
        <label htmlFor="note" className="h3 block">
          Note interne
        </label>
        <textarea id="note" name="note" rows={3} maxLength={2000} defaultValue={order.adminNote ?? ""} className="input" />
        <SubmitButton className="btn btn-soft btn-sm">Enregistrer la note</SubmitButton>
      </ActionForm>

      <section>
        <h2 className="h3 mb-3">Historique</h2>
        <ol className="card divide-y divide-line">
          {events.map((event) => (
            <li key={event.id} className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 py-3 text-sm">
              <span className="min-w-0 [overflow-wrap:anywhere]">{event.message}</span>
              <span className="text-xs whitespace-nowrap text-muted">
                {event.actor} · {formatDateTime(event.createdAt)}
              </span>
            </li>
          ))}
        </ol>
      </section>
    </div>
  );
}
