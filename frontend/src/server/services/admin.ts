import "server-only";
import { and, desc, eq, gte, inArray, like, or, sql } from "drizzle-orm";
import { db, schema } from "@/server/db";
import { ORDER_STATUSES, PAID_STATUSES, type OrderStatus } from "@/lib/enums";

/** Requêtes de lecture du backoffice. */

const escapeLike = (v: string) => `%${v.replace(/[\\%_]/g, (c) => `\\${c}`)}%`;

export async function dashboardStats() {
  const since = (days: number) => new Date(Date.now() - days * 86_400_000);
  const revenue = async (from: Date) => {
    const [row] = await db()
      .select({
        orders: sql<number>`COUNT(*)`,
        total: sql<number>`COALESCE(SUM(${schema.orders.totalXof}), 0)`,
        // Coût converti au taux enregistré sur chaque commande.
        cost: sql<number>`COALESCE(SUM(CEIL(${schema.orders.unitCostUsdCents} * ${schema.orders.quantity} * ${schema.orders.usdRate} / 100)), 0)`,
      })
      .from(schema.orders)
      .where(and(eq(schema.orders.status, "delivered"), gte(schema.orders.paidAt, from)));
    return { orders: Number(row?.orders ?? 0), total: Number(row?.total ?? 0), margin: Number(row?.total ?? 0) - Number(row?.cost ?? 0) };
  };
  const byStatus = await db().select({ status: schema.orders.status, count: sql<number>`COUNT(*)` }).from(schema.orders).groupBy(schema.orders.status);
  const counts = Object.fromEntries(ORDER_STATUSES.map((s) => [s, 0])) as Record<OrderStatus, number>;
  for (const row of byStatus) counts[row.status] = Number(row.count);
  const [tickets] = await db().select({ open: sql<number>`COUNT(*)` }).from(schema.tickets).where(eq(schema.tickets.status, "open"));
  const [customers] = await db().select({ total: sql<number>`COUNT(*)` }).from(schema.users).where(eq(schema.users.role, "customer"));
  return { today: await revenue(since(1)), week: await revenue(since(7)), month: await revenue(since(30)), counts, openTickets: Number(tickets?.open ?? 0), customers: Number(customers?.total ?? 0) };
}

export const ORDER_FILTERS = {
  toutes: null,
  "a-traiter": ["failed"],
  "en-cours": ["paid", "processing", "activation_awaiting"],
  livrees: ["delivered"],
  "en-attente": ["pending_payment"],
  closes: ["payment_failed", "expired", "cancelled", "refunded"],
} as const satisfies Record<string, readonly OrderStatus[] | null>;
export type OrderFilter = keyof typeof ORDER_FILTERS;

export async function adminListOrders(filter: OrderFilter, q?: string) {
  const statuses = ORDER_FILTERS[filter];
  const term = q?.trim().slice(0, 80);
  const conditions = [];
  if (statuses) conditions.push(inArray(schema.orders.status, [...statuses]));
  if (term) conditions.push(or(like(schema.orders.reference, escapeLike(term)), like(schema.users.email, escapeLike(term)), like(schema.orders.productName, escapeLike(term))));
  return db()
    .select({ order: schema.orders, email: schema.users.email })
    .from(schema.orders)
    .innerJoin(schema.users, eq(schema.users.id, schema.orders.userId))
    .where(conditions.length ? and(...conditions) : undefined)
    .orderBy(desc(schema.orders.createdAt))
    .limit(300);
}

export async function adminListCustomers(q?: string) {
  const term = q?.trim().slice(0, 80);
  return db()
    .select({
      user: schema.users,
      orders: sql<number>`(SELECT COUNT(*) FROM orders o WHERE o.user_id = ${schema.users.id} AND o.status IN (${sql.join(
        PAID_STATUSES.map((s) => sql`${s}`),
        sql`, `,
      )}))`,
      spent: sql<number>`(SELECT COALESCE(SUM(o.total_xof), 0) FROM orders o WHERE o.user_id = ${schema.users.id} AND o.status = 'delivered')`,
    })
    .from(schema.users)
    .where(term ? or(like(schema.users.email, escapeLike(term)), like(schema.users.lastName, escapeLike(term)), like(schema.users.phone, escapeLike(term))) : undefined)
    .orderBy(desc(schema.users.createdAt))
    .limit(300);
}

export async function adminListAudit() {
  return db().select().from(schema.auditLogs).orderBy(desc(schema.auditLogs.createdAt)).limit(300);
}
