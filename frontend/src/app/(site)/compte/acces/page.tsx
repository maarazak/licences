import type { Metadata } from "next";
import Link from "next/link";
import { DeliveryList } from "@/components/delivery-list";
import { EmptyState, PageHeader } from "@/components/ui";
import { formatDate } from "@/lib/format";
import { requireUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";
import { listUserOrders, readDelivery } from "@/server/services/orders";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Mes accès"), robots: { index: false } };
}

export default async function AccessPage() {
  const user = await requireUser("/compte/acces");
  const { t } = await getI18n();
  const delivered = (await listUserOrders(user.id)).filter((o) => o.status === "delivered");
  return (
    <div className="space-y-6">
      <PageHeader title={t("Mes accès")} description={t("Réservé au propriétaire connecté de chaque commande. Un numéro de commande seul n'y donne jamais accès.")} />
      {delivered.length ? (
        delivered.map((order) => {
          const items = readDelivery(order);
          return (
            <section key={order.id} className="card p-5">
              <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="h3">{order.productName}</h2>
                <Link href={`/compte/achats/${order.id}`} className="link text-xs">
                  {order.reference} · {t("livrée le {date}", { date: formatDate(order.deliveredAt) })}
                </Link>
              </div>
              {items.length ? <DeliveryList items={items} /> : <p className="alert alert-warning">{t("Accès indisponibles pour le moment. Contactez l'assistance depuis la commande.")}</p>}
            </section>
          );
        })
      ) : (
        <EmptyState title={t("Aucun accès disponible pour l'instant")} description={t("Vos accès apparaîtront ici après confirmation du paiement et livraison.")} action={{ href: "/compte/achats", label: t("Voir mes achats") }} />
      )}
    </div>
  );
}
