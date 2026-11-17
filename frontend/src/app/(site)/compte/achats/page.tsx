import { Search } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { OrdersTable } from "@/components/orders-table";
import { EmptyState, PageHeader } from "@/components/ui";
import type { OrderStatus } from "@/lib/enums";
import { requireUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";
import { listUserOrders } from "@/server/services/orders";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Mes achats"), robots: { index: false } };
}

const TABS: Array<{ key: string; label: string; statuses: OrderStatus[] | null }> = [
  { key: "", label: "Toutes", statuses: null },
  { key: "attente", label: "En attente", statuses: ["pending_payment"] },
  { key: "preparation", label: "En préparation", statuses: ["paid", "processing", "activation_awaiting", "failed"] },
  { key: "livrees", label: "Livrées", statuses: ["delivered"] },
];

export default async function OrdersPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const params = await searchParams;
  const user = await requireUser("/compte/achats");
  const { t } = await getI18n();
  const q = (Array.isArray(params.q) ? (params.q[0] ?? "") : (params.q ?? "")).trim().slice(0, 80).toLowerCase();
  const tabKey = Array.isArray(params.statut) ? (params.statut[0] ?? "") : (params.statut ?? "");
  const tab = TABS.find((x) => x.key === tabKey) ?? TABS[0];

  const all = await listUserOrders(user.id);
  const orders = all.filter((o) => (!tab.statuses || tab.statuses.includes(o.status)) && (!q || o.reference.toLowerCase().includes(q) || o.productName.toLowerCase().includes(q)));

  return (
    <div className="space-y-6">
      <PageHeader title={t("Mes achats")} />
      <form method="get" className="flex flex-wrap items-end gap-3">
        {tab.key ? <input type="hidden" name="statut" value={tab.key} /> : null}
        <div className="relative min-w-0 flex-1 basis-60">
          <label htmlFor="q" className="sr-only">
            {t("Rechercher une commande")}
          </label>
          <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
          <input id="q" name="q" type="search" defaultValue={q} maxLength={80} placeholder={t("Référence ou produit")} className="input pl-10" />
        </div>
        <button type="submit" className="btn btn-soft">
          {t("Rechercher")}
        </button>
      </form>
      <div className="flex flex-wrap gap-2" role="group" aria-label={t("Filtrer par statut")}>
        {TABS.map((x) => (
          <Link key={x.key} href={x.key ? `/compte/achats?statut=${x.key}` : "/compte/achats"} aria-current={x.key === tab.key ? "page" : undefined} className={`chip ${x.key === tab.key ? "chip-active" : ""}`}>
            {t(x.label)}
          </Link>
        ))}
      </div>
      {orders.length ? (
        <OrdersTable orders={orders} />
      ) : (
        <EmptyState title={all.length ? t("Aucune commande ne correspond") : t("Aucune commande pour l'instant")} description={all.length ? t("Modifiez la recherche ou le filtre.") : t("Vos achats apparaîtront ici dès votre première commande.")} action={{ href: "/boutique", label: t("Voir la boutique") }} />
      )}
    </div>
  );
}
