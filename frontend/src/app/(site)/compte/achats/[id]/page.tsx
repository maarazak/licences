import { ArrowLeft, KeyRound } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { eq } from "drizzle-orm";
import { AutoRefresh } from "@/components/auto-refresh";
import { DeliveryList } from "@/components/delivery-list";
import { Details } from "@/components/ui";
import { OrderStatusBadge } from "@/components/status-badges";
import { formatDateTime, formatWarranty, formatXof } from "@/lib/format";
import { productInstructions } from "@/lib/product-text";
import { requireUser } from "@/server/auth/session";
import { db, schema } from "@/server/db";
import { getI18n } from "@/server/i18n";
import { getUserOrder, readDelivery, syncPaymentThrottled } from "@/server/services/orders";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Commande"), robots: { index: false } };
}

const NOTES: Partial<Record<string, string>> = {
  pending_payment: "Confirmation du paiement en attente.",
  paid: "Paiement reçu. Nous préparons vos accès.",
  processing: "Paiement reçu. Nous préparons vos accès.",
  activation_awaiting: "Paiement reçu. L'activation manuelle est en cours ; un e-mail vous préviendra.",
  payment_failed: "Le paiement n'a pas abouti. Aucun montant n'a été retenu pour cette commande.",
  expired: "La session de paiement a expiré sans paiement.",
  cancelled: "Commande annulée. Aucun montant n'a été débité.",
};

export default async function OrderPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const user = await requireUser(`/compte/achats/${id}`);
  const { t, lang } = await getI18n();
  let order = await getUserOrder(user.id, id);
  if (!order) notFound();
  if (order.status === "pending_payment") {
    await syncPaymentThrottled(order.id);
    order = (await getUserOrder(user.id, id)) ?? order;
  }

  const items = readDelivery(order);
  const [product] = await db()
    .select({ instructions: schema.products.instructions, instructionsEn: schema.products.instructionsEn, supplierDescription: schema.products.supplierDescription, slug: schema.products.slug })
    .from(schema.products)
    .where(eq(schema.products.id, order.productId))
    .limit(1);
  const inProgress = ["pending_payment", "paid", "processing"].includes(order.status);
  const note = NOTES[order.status];
  const guide = product ? productInstructions({ ...product, description: null, descriptionEn: null }, lang) : "";

  return (
    <div className="space-y-6">
      {inProgress ? <AutoRefresh seconds={8} /> : null}
      <nav aria-label={t("Fil d'Ariane")} className="text-xs font-medium text-muted">
        <Link href="/compte/achats" className="inline-flex items-center gap-1 hover:text-ink">
          <ArrowLeft className="size-3.5" aria-hidden="true" />
          {t("Mes achats")}
        </Link>{" "}
        / <span className="text-ink">{order.reference}</span>
      </nav>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="h1">{order.productName}</h1>
        <OrderStatusBadge status={order.status} />
      </div>

      {note ? <p className="alert alert-info">{t(note)}</p> : null}

      {order.status === "pending_payment" ? (
        <Link href={`/paiement/reprendre?commande=${order.id}`} className="btn btn-primary">
          {t("Reprendre le paiement")}
        </Link>
      ) : null}

      {order.status === "delivered" ? (
        <section className="card p-5 shadow-pop">
          <h2 className="h3 flex items-center gap-2 text-lg">
            <KeyRound className="size-5 text-brand" aria-hidden="true" />
            {t("Vos accès")}
          </h2>
          <p className="hint mb-4">{t("Réservés à votre compte. Ne les partagez pas.")}</p>
          {items.length ? <DeliveryList items={items} /> : <p className="alert alert-warning">{t("Les accès ne peuvent pas être affichés pour le moment. Contactez l'assistance depuis cette commande.")}</p>}
          {guide ? (
            <>
              <h3 className="h3 mt-6">{t("Mode d'emploi")}</h3>
              <p className="prose-plain mt-2">{guide}</p>
            </>
          ) : null}
        </section>
      ) : null}

      {order.status === "failed" ? (
        <section className="card space-y-3 border-l-4 border-l-accent p-5">
          <h2 className="h3">{t("Votre paiement est confirmé")}</h2>
          <p className="text-sm leading-relaxed text-muted">
            {t("La livraison automatique de votre licence a rencontré un délai. Notre équipe est prévenue et s'en occupe personnellement.")}
          </p>
          <p className="alert alert-info">
            {t("Vous n'avez rien à faire : vos accès vous seront livrés dès validation par notre équipe.")}
          </p>
        </section>
      ) : null}

      {order.status === "refunded" ? (
        <p className="alert alert-success">
          {order.refundMethod === "balance"
            ? t("{amount} ont été crédités sur votre solde par notre équipe.", { amount: formatXof(order.totalXof) })
            : t("Cette commande a été remboursée sur votre moyen de paiement.")}
        </p>
      ) : null}

      <section className="card px-5 py-2">
        <h2 className="sr-only">{t("Résumé")}</h2>
        <Details
          rows={[
            [t("Référence"), <span key="r" className="font-mono text-xs">{order.reference}</span>],
            [t("Date"), formatDateTime(order.createdAt)],
            [t("Quantité"), String(order.quantity)],
            [t("Montant"), formatXof(order.totalXof)],
            [t("Paiement"), order.paidWith === "balance" ? t("Solde du compte") : t("Mobile Money")],
            [t("Durée"), t(order.expireDays ? (order.expireDays % 365 === 0 ? (order.expireDays === 365 ? "1 an" : `${order.expireDays / 365} ans`) : order.expireDays % 30 === 0 ? (order.expireDays === 30 ? "1 mois" : `${order.expireDays / 30} mois`) : `${order.expireDays} jours`) : "Selon l'offre")],
            [t("Garantie"), t(formatWarranty(order.warranty))],
            ...(order.activationEmails?.length ? ([[t("Adresse(s) activée(s)"), order.activationEmails.join(", ")]] as Array<[string, string]>) : []),
            ...(order.deliveredAt ? ([[t("Livrée le"), formatDateTime(order.deliveredAt)]] as Array<[string, string]>) : []),
          ]}
        />
      </section>

      <div className="flex flex-wrap gap-3">
        <Link href={`/compte/assistance/nouveau?commande=${order.id}`} className="btn btn-soft">
          {t("Demander de l'aide sur cette commande")}
        </Link>
        {product ? (
          <Link href={`/boutique/${product.slug}`} className="btn btn-soft">
            {t("Voir l'offre")}
          </Link>
        ) : null}
      </div>
    </div>
  );
}
