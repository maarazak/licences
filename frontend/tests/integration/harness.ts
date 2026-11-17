/**
 * Banc d'essai d'intégration : faux fournisseur Flowseller + faux Orqex en mémoire (même protocole que
 * scripts/dev-mock.mjs, avec des commandes de pilotage en plus), base MySQL `licences_test`.
 */
import { randomUUID } from "node:crypto";
import { vi } from "vitest";

if (!/\/licences_test$/.test(process.env.DATABASE_URL ?? "")) throw new Error("Les tests d'intégration exigent la base licences_test");

// ─── Faux services externes ────────────────────────────────────────────────

export type FakeProduct = {
  id: string;
  slug: string;
  name: string;
  category: string;
  image_url: null;
  delivery_kind: string;
  price: number;
  list_price: number;
  stock: number | null;
  in_stock: boolean;
  preorder: boolean;
  expire_days: number;
  warranty: string;
  requires_email: boolean;
  purchasable: boolean;
};

type Checkout = { id: string; paymentId: string; amount: number; reference: string; url: string; payment: string; checkoutStatus: string; amountOverride?: number; currency?: string; paymentIdOverride?: string };

export const fake = {
  balance: 1000,
  /** completed | activation_awaiting | delivery_failed | insufficient_stock | timeout_after_purchase | network_error | empty */
  nextOrder: "completed" as string,
  supplierDelayMs: 0,
  orqexDown: false,
  products: [] as FakeProduct[],
  supplierOrders: new Map<string, { id: string; status: string; items: string[]; total: number; quantity: number }>(),
  idem: new Map<string, string>(),
  /** Nombre d'achats réellement débités chez le fournisseur. */
  purchases: 0,
  createOrderCalls: 0,
  checkouts: new Map<string, Checkout>(),
  orqexReads: 0,
  reset() {
    this.balance = 1000;
    this.nextOrder = "completed";
    this.supplierDelayMs = 0;
    this.orqexDown = false;
    this.products = [product("sup-1", "netflix-1-mois", "Netflix 1 mois", 3)];
    this.supplierOrders.clear();
    this.idem.clear();
    this.purchases = 0;
    this.createOrderCalls = 0;
    this.checkouts.clear();
    this.orqexReads = 0;
  },
  /** Change l'état du paiement de toutes les sessions (ou d'une seule). */
  setPayment(payment: string, checkoutStatus?: string, checkoutId?: string) {
    for (const c of this.checkouts.values()) {
      if (checkoutId && c.id !== checkoutId) continue;
      c.payment = payment;
      c.checkoutStatus = checkoutStatus ?? (payment === "pending" ? "open" : payment === "expired" ? "expired" : payment === "completed" ? "completed" : "open");
    }
  },
};

export function product(id: string, slug: string, name: string, price: number, extra: Partial<FakeProduct> = {}): FakeProduct {
  return { id, slug, name, category: "accounts", image_url: null, delivery_kind: "account", price, list_price: price, stock: 50, in_stock: true, preorder: false, expire_days: 30, warranty: "30 days", requires_email: false, purchasable: true, ...extra };
}

const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { "content-type": "application/json" } });
const err = (status: number, code: string, message: string) => json(status, { error: { code, message } });
const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

async function fakeFetch(input: string | URL | Request, init: RequestInit = {}): Promise<Response> {
  const url = new URL(typeof input === "string" ? input : input instanceof URL ? input.href : input.url);
  if (url.hostname !== "fake.test") throw new Error(`Appel réseau réel interdit dans les tests : ${url.href}`);
  const headers = new Headers(init.headers);
  const method = (init.method ?? "GET").toUpperCase();
  const body = typeof init.body === "string" && init.body ? JSON.parse(init.body) : {};
  const path = url.pathname;

  if (path.startsWith("/supplier/api/v1")) {
    const p = path.slice("/supplier/api/v1".length);
    if (p === "/products") return json(200, { data: fake.products, pagination: { has_more: false } });
    if (p.startsWith("/products/")) {
      const key = decodeURIComponent(p.slice(10));
      const prod = fake.products.find((x) => x.id === key || x.slug === key);
      return prod ? json(200, { data: prod }) : err(404, "product_not_found", "Produit inconnu");
    }
    if (p === "/balance") return json(200, { balance: fake.balance, currency: "USD" });
    if (p === "/orders" && method === "POST") {
      fake.createOrderCalls++;
      if (fake.supplierDelayMs) await sleep(fake.supplierDelayMs);
      const key = headers.get("idempotency-key");
      if (key && fake.idem.has(key)) return json(200, { data: { ...fake.supplierOrders.get(fake.idem.get(key)!), idempotent_replay: true } });
      if (fake.nextOrder === "network_error") throw new TypeError("fetch failed");
      const prod = fake.products.find((x) => x.id === body.product);
      if (!prod) return err(404, "product_not_found", "Produit inconnu");
      const quantity = body.quantity ?? 1;
      const total = Math.round(prod.price * quantity * 100) / 100;
      if (fake.nextOrder === "insufficient_stock") return err(409, "insufficient_stock", "Stock insuffisant");
      if (fake.balance < total) return err(402, "insufficient_balance", "Solde insuffisant");
      if (fake.nextOrder === "delivery_failed") return err(502, "delivery_failed", "Livraison impossible, remboursé");
      fake.balance = Math.round((fake.balance - total) * 100) / 100;
      fake.purchases++;
      const awaiting = fake.nextOrder === "activation_awaiting";
      const order = {
        id: randomUUID().replace(/-/g, "").slice(0, 24),
        reference: `SUP-${fake.purchases}`,
        status: awaiting ? "activation_awaiting" : "completed",
        quantity,
        total,
        items: awaiting || fake.nextOrder === "empty" ? [] : Array.from({ length: quantity }, (_, i) => `compte${i + 1}@exemple.test | MotDePasse-${i + 1}`),
      };
      fake.supplierOrders.set(order.id, order);
      if (key) fake.idem.set(key, order.id);
      // Achat débité mais réponse perdue (coupure réseau) : l'issue est incertaine pour la boutique.
      if (fake.nextOrder === "timeout_after_purchase") throw new DOMException("The operation was aborted due to timeout", "TimeoutError");
      return json(200, { data: order });
    }
    if (p.startsWith("/orders/")) {
      const o = fake.supplierOrders.get(p.slice(8));
      return o ? json(200, { data: o }) : err(404, "order_not_found", "Commande inconnue");
    }
  }

  if (path.startsWith("/pawapay/v2") || path.startsWith("/orqex/v1")) {
    if (fake.orqexDown) throw new TypeError("fetch failed");
    const p = path.startsWith("/pawapay/v2") ? path.slice("/pawapay/v2".length) : path.slice("/orqex/v1".length);
    if ((p === "/paymentpage" || p === "/payment/checkouts") && method === "POST") {
      const id = body.depositId || `cs_${randomUUID().slice(0, 12)}`;
      const amount = body.amountDetails?.amount !== undefined ? Number(body.amountDetails.amount) : body.amount;
      const c: Checkout = {
        id,
        paymentId: id,
        reference: body.customerMessage || body.reference || "CMD",
        amount,
        url: `https://paywith.sandbox.pawapay.io/?token=${id}`,
        payment: "pending",
        checkoutStatus: "ACCEPTED",
      };
      fake.checkouts.set(id, c);
      return json(200, { depositId: id, status: "ACCEPTED", redirectUrl: c.url });
    }
    if (p.startsWith("/deposits/") || p.startsWith("/payment/checkouts/")) {
      fake.orqexReads++;
      const prefix = p.startsWith("/deposits/") ? "/deposits/" : "/payment/checkouts/";
      const depositId = decodeURIComponent(p.slice(prefix.length));
      const c = fake.checkouts.get(depositId);
      if (!c) return json(404, { status: "NOT_FOUND", message: "Not found." });
      const value = c.amountOverride ?? c.amount;
      let status = "ACCEPTED";
      if (c.payment === "completed") status = "COMPLETED";
      else if (c.payment === "expired") status = "EXPIRED";
      else if (c.payment === "failed") status = "FAILED";
      const returnedDepositId = c.paymentIdOverride ?? c.id;
      return json(200, {
        status: "FOUND",
        data: {
          depositId: returnedDepositId,
          status,
          amount: String(value),
          currency: c.currency ?? "XOF",
          country: "CIV",
          providerTransactionId: returnedDepositId,
          payer: { type: "MMO", accountDetails: { phoneNumber: "2250700000000", provider: "MTN_MOMO_CIV" } },
          customerMessage: c.reference,
        },
      });
    }
  }
  return json(404, { message: "route inconnue" });
}

vi.stubGlobal("fetch", fakeFetch);

// ─── Base de test ──────────────────────────────────────────────────────────

const { db, schema } = await import("@/server/db");
const { newId } = await import("@/server/security/crypto");
const { eq, sql } = await import("drizzle-orm");

export { db, schema, eq, sql };

export async function resetDb(): Promise<void> {
  for (const t of ["orders", "order_events", "wallet_deposits", "wallet_entries", "users", "products", "rate_limits", "settings", "stock_alerts"]) {
    await db().execute(sql.raw(`DELETE FROM \`${t}\``));
  }
  fake.reset();
}

export async function createUser(extra: Partial<typeof schema.users.$inferInsert> = {}) {
  const id = newId();
  await db()
    .insert(schema.users)
    .values({ id, email: `client-${id.slice(0, 8)}@exemple.test`, createdAt: new Date(), ...extra });
  const [u] = await db().select().from(schema.users).where(eq(schema.users.id, id));
  return u;
}

/** Insère le produit du faux fournisseur dans la base, publié. */
export async function createProduct(p: FakeProduct = fake.products[0], extra: Partial<typeof schema.products.$inferInsert> = {}) {
  const id = newId();
  const now = new Date();
  await db()
    .insert(schema.products)
    .values({
      id,
      supplierId: p.id,
      slug: p.slug,
      name: p.name,
      costUsdCents: Math.round(p.price * 100),
      stock: p.stock,
      inStock: p.in_stock,
      supplierPurchasable: p.purchasable,
      requiresEmail: p.requires_email,
      expireDays: p.expire_days,
      isPublished: true,
      syncedAt: now,
      createdAt: now,
      updatedAt: now,
      ...extra,
    });
  const [row] = await db().select().from(schema.products).where(eq(schema.products.id, id));
  return row;
}

export async function getOrder(id: string) {
  const [o] = await db().select().from(schema.orders).where(eq(schema.orders.id, id));
  return o;
}

export async function events(orderId: string, type?: string) {
  const rows = await db().select().from(schema.orderEvents).where(eq(schema.orderEvents.orderId, orderId));
  return type ? rows.filter((r) => r.type === type) : rows;
}

export async function balanceOf(userId: string): Promise<number> {
  const [u] = await db().select({ b: schema.users.balanceXof }).from(schema.users).where(eq(schema.users.id, userId));
  return u.b;
}

export async function setOrder(id: string, patch: Partial<typeof schema.orders.$inferInsert>) {
  await db().update(schema.orders).set(patch).where(eq(schema.orders.id, id));
}

export function checkoutInput(productId: string, totalXof: number, extra: Record<string, unknown> = {}) {
  return { productId, quantity: 1, firstName: "Awa", lastName: "Traoré", phone: "70000000", country: "BF", activationEmails: [] as string[], payWithBalance: false, expectedTotalXof: totalXof, ...extra };
}
