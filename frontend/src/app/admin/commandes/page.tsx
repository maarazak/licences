import type { Metadata } from "next";
import Link from "next/link";
import { EmptyState, PageHeader } from "@/components/ui";
import { OrderStatusBadge } from "@/components/status-badges";
import { formatDateTime, formatXof } from "@/lib/format";
import { adminListOrders, ORDER_FILTERS, type OrderFilter } from "@/server/services/admin";
import { requireAdmin } from "@/server/auth/session";

export const metadata: Metadata = { title: "Commandes" };

const LABELS: Record<OrderFilter, string> = { toutes: "Toutes", "a-traiter": "À traiter", "en-cours": "En cours", livrees: "Livrées", "en-attente": "Paiement en attente", closes: "Closes" };

export default async function AdminOrdersPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  // Le layout ne suffit pas (il ne se ré-exécute pas à chaque navigation) : chaque page vérifie le droit elle-même.
  await requireAdmin("/admin");
  const params = await searchParams;
  const raw = Array.isArray(params.filtre) ? (params.filtre[0] ?? "") : (params.filtre ?? "");
  const filter: OrderFilter = raw in ORDER_FILTERS ? (raw as OrderFilter) : "toutes";
  const q = (Array.isArray(params.q) ? (params.q[0] ?? "") : (params.q ?? "")).slice(0, 80);
  const rows = await adminListOrders(filter, q);

  return (
    <div className="space-y-6">
      <PageHeader title="Commandes" />
      <div className="flex flex-wrap gap-2" role="group" aria-label="Filtrer par statut">
        {(Object.keys(ORDER_FILTERS) as OrderFilter[]).map((key) => (
          <Link key={key} href={key === "toutes" ? "/admin/commandes" : `/admin/commandes?filtre=${key}`} aria-current={key === filter ? "page" : undefined} className={`btn btn-sm ${key === filter ? "btn-brand" : "btn-soft"}`}>
            {LABELS[key]}
          </Link>
        ))}
      </div>
      <form method="get" className="flex flex-wrap items-end gap-3">
        {filter !== "toutes" ? <input type="hidden" name="filtre" value={filter} /> : null}
        <div className="min-w-0 flex-1 basis-60">
          <label htmlFor="q" className="label">
            Rechercher
          </label>
          <input id="q" name="q" type="search" defaultValue={q} maxLength={80} placeholder="Référence, e-mail ou produit" className="input" />
        </div>
        <button type="submit" className="btn btn-soft">
          Rechercher
        </button>
      </form>
      {rows.length ? (
        <div className="table-wrap">
          <table className="table">
            <caption className="sr-only">Commandes</caption>
            <thead>
              <tr>
                <th scope="col">Référence</th>
                <th scope="col">Client</th>
                <th scope="col">Produit</th>
                <th scope="col" className="text-right">
                  Montant
                </th>
                <th scope="col">Statut</th>
                <th scope="col">Date</th>
              </tr>
            </thead>
            <tbody>
              {rows.map(({ order, email }) => (
                <tr key={order.id}>
                  <td>
                    <Link href={`/admin/commandes/${order.id}`} className="link font-mono text-xs">
                      {order.reference}
                    </Link>
                  </td>
                  <td className="max-w-48 truncate text-muted">{email}</td>
                  <td>
                    {order.productName}
                    {order.quantity > 1 ? <span className="text-muted"> × {order.quantity}</span> : null}
                  </td>
                  <td className="text-right font-semibold whitespace-nowrap">{formatXof(order.totalXof)}</td>
                  <td>
                    <OrderStatusBadge status={order.status} admin />
                    {order.refundRequestedAt && order.status === "failed" ? <span className="mt-1 block text-xs text-danger">Remboursement demandé</span> : null}
                  </td>
                  <td className="whitespace-nowrap text-muted">{formatDateTime(order.createdAt)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <EmptyState title="Aucune commande" description="Aucune commande ne correspond à ce filtre." />
      )}
    </div>
  );
}
