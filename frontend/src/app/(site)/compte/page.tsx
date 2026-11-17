import { Bell, ChevronRight, KeyRound, LifeBuoy, Package, Wallet } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { OrdersTable } from "@/components/orders-table";
import { EmptyState, Stat } from "@/components/ui";
import { formatXof } from "@/lib/format";
import { requireUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";
import { countActiveAlerts } from "@/server/services/alerts";
import { listUserOrders } from "@/server/services/orders";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Mon compte"), robots: { index: false } };
}

export default async function AccountDashboardPage() {
  const user = await requireUser("/compte");
  const [orders, alerts, { t }] = await Promise.all([listUserOrders(user.id), countActiveAlerts(user.id), getI18n()]);
  const delivered = orders.filter((o) => o.status === "delivered").length;
  const pending = orders.find((o) => o.status === "pending_payment" && o.paymentUrl);
  const toResolve = orders.find((o) => o.status === "failed");

  const banner = (title: string, order: (typeof orders)[number]) => (
    <Link href={`/compte/achats/${order.id}`} className="card group flex flex-wrap items-center justify-between gap-4 border-l-4 border-l-accent p-4 transition duration-300 hover:-translate-y-0.5 hover:shadow-pop">
      <div>
        <p className="h3">{title}</p>
        <p className="mt-0.5 text-sm text-muted">
          {order.reference} · {order.productName} · {formatXof(order.totalXof)}
        </p>
      </div>
      <span className="btn btn-brand btn-sm">
        {t("Voir la commande")}
        <ChevronRight className="size-4" aria-hidden="true" />
      </span>
    </Link>
  );

  return (
    <div className="space-y-8">
      <div>
        <h1 className="h1">{user.firstName ? t("Bonjour {name}", { name: user.firstName }) : t("Bonjour")}</h1>
        <p className="lead mt-2">{t("Vos achats, vos accès, votre solde et vos alertes au même endroit.")}</p>
      </div>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Stat Icon={Wallet} label={t("Mon solde")} value={formatXof(user.balanceXof)} href="/compte/solde" />
        <Stat Icon={Package} label={t("Commandes")} value={String(orders.length)} href="/compte/achats" />
        <Stat Icon={KeyRound} label={t("Accès livrés")} value={String(delivered)} href="/compte/acces" />
        <Stat Icon={Bell} label={t("Alertes actives")} value={String(alerts)} href="/compte/alertes" />
      </div>

      {toResolve ? banner(t("Commande payée en cours de préparation par notre équipe"), toResolve) : null}
      {pending ? banner(t("Paiement en attente"), pending) : null}

      <section>
        <div className="mb-3 flex items-end justify-between gap-3">
          <h2 className="h2">{t("Dernières commandes")}</h2>
          <Link href="/compte/achats" className="link text-sm">
            {t("Tous mes achats")}
          </Link>
        </div>
        {orders.length ? <OrdersTable orders={orders.slice(0, 5)} /> : <EmptyState title={t("Aucune commande pour l'instant")} description={t("Vos achats apparaîtront ici dès votre première commande.")} action={{ href: "/boutique", label: t("Voir la boutique") }} />}
      </section>

      <div className="card flex flex-wrap items-center justify-between gap-4 p-4">
        <p className="flex items-center gap-3 text-sm font-semibold">
          <LifeBuoy className="size-5 text-brand" aria-hidden="true" />
          {t("Besoin d'aide sur une commande ?")}
        </p>
        <Link href="/compte/assistance" className="btn btn-soft btn-sm">
          {t("Contacter l'assistance")}
        </Link>
      </div>
    </div>
  );
}
