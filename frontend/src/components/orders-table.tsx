import Link from "next/link";
import { formatDate, formatXof } from "@/lib/format";
import { getI18n } from "@/server/i18n";
import type { Order } from "@/server/db/schema";
import { OrderStatusBadge } from "./status-badges";

export async function OrdersTable({ orders }: { orders: Order[] }) {
  const { t } = await getI18n();
  return (
    <div className="table-wrap">
      <table className="table">
        <caption className="sr-only">{t("Commandes")}</caption>
        <thead>
          <tr>
            <th scope="col">{t("Produit")}</th>
            <th scope="col">{t("Référence")}</th>
            <th scope="col">{t("Date")}</th>
            <th scope="col" className="text-right">
              {t("Montant")}
            </th>
            <th scope="col">{t("Statut")}</th>
            <th scope="col">
              <span className="sr-only">{t("Action")}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {orders.map((order) => (
            <tr key={order.id}>
              <td className="font-semibold">
                {order.productName}
                {order.quantity > 1 ? <span className="text-muted"> × {order.quantity}</span> : null}
              </td>
              <td className="font-mono text-xs text-muted">{order.reference}</td>
              <td className="whitespace-nowrap text-muted">{formatDate(order.createdAt)}</td>
              <td className="text-right font-semibold whitespace-nowrap">{formatXof(order.totalXof)}</td>
              <td>
                <OrderStatusBadge status={order.status} />
              </td>
              <td className="text-right">
                <Link href={`/compte/achats/${order.id}`} className="link text-xs" aria-label={`${t("Détail de la commande")} ${order.reference}`}>
                  {t("Détail")}
                </Link>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
