import "server-only";
import { z } from "zod";
import { env } from "@/server/env";

/**
 * Client de l'API revendeur Flowseller.
 * Doc : https://flowseller-api.pe-supplykh.com/docs
 * Limites : 60 requêtes/min par clé, 10 commandes/min. Devise : USD uniquement.
 */

const num = z.coerce.number();
/** Booléen tolérant : true / 1 / "1" / "true". Une valeur absente vaut false. */
const bool = z.preprocess((v) => v === true || v === 1 || v === "1" || v === "true", z.boolean());
/** Entier facultatif : absent, null ou vide = null (quantité non dénombrable) ; "10" est accepté. */
const optInt = z.preprocess((v) => (v === null || v === undefined || v === "" ? null : Number(v)), z.number().int().nullable());

const productSchema = z.object({
  id: z.string().min(1),
  slug: z.string().min(1),
  name: z.string().min(1),
  category: z.string().nullish(),
  image_url: z.string().nullish(),
  description: z.string().nullish(),
  delivery_kind: z.string().nullish(),
  price: num,
  list_price: num.nullish(),
  stock: optInt,
  in_stock: bool,
  preorder: bool,
  expire_days: z.coerce.number().int().nullish(),
  warranty: z.string().nullish(),
  requires_email: bool,
  purchasable: bool,
});
export type SupplierProduct = z.infer<typeof productSchema>;

const orderSchema = z.object({
  id: z.string().min(1),
  reference: z.string().nullish(),
  status: z.string(),
  quantity: z.coerce.number().int().nullish(),
  total: num.nullish(),
  items: z.array(z.unknown()).nullish(),
  idempotent_replay: z.boolean().nullish(),
});
export type SupplierOrder = z.infer<typeof orderSchema>;

const balanceSchema = z.object({ balance: num, currency: z.string().default("USD"), username: z.string().nullish(), orders_count: z.coerce.number().nullish() });
export type SupplierBalance = z.infer<typeof balanceSchema>;

/** Codes d'erreur après lesquels rien n'a été débité et où réessayer tel quel est inutile. */
const DEFINITIVE_CODES = new Set([
  "invalid_request",
  "invalid_quantity",
  "quantity_too_large",
  "emails_required",
  "invalid_email",
  "duplicate_email",
  "variation_not_supported",
  "emails_count_mismatch",
  "unauthorized",
  "insufficient_balance",
  "account_blocked",
  "product_not_found",
  "order_not_found",
  "product_inactive",
  "insufficient_stock",
  "preorder_not_supported",
  "delivery_failed",
  "shop_closed",
]);

export class SupplierError extends Error {
  constructor(
    readonly status: number,
    readonly code: string,
    message: string,
    readonly retryAfterSeconds = 0,
  ) {
    super(message);
    this.name = "SupplierError";
  }

  /** true : l'issue est certaine (échec net). false : issue inconnue ou temporaire, il faut réessayer avec la même clé d'idempotence. */
  get definitive(): boolean {
    return DEFINITIVE_CODES.has(this.code);
  }
}

async function call(path: string, init: { method?: "GET" | "POST"; body?: unknown; idempotencyKey?: string; timeoutMs?: number } = {}): Promise<unknown> {
  const e = env();
  if (!e.SUPPLIER_API_KEY) throw new SupplierError(0, "not_configured", "SUPPLIER_API_KEY est absent du fichier .env");

  const headers: Record<string, string> = { authorization: `Bearer ${e.SUPPLIER_API_KEY}`, accept: "application/json" };
  if (init.body !== undefined) headers["content-type"] = "application/json";
  if (init.idempotencyKey) headers["idempotency-key"] = init.idempotencyKey;

  // Les lectures (GET) sont sans effet : une coupure ou un délai ponctuel justifie une seconde tentative. Jamais pour un achat (POST).
  const attempts = (init.method ?? "GET") === "GET" ? 2 : 1;
  let res: Response | undefined;
  for (let attempt = 1; attempt <= attempts; attempt++) {
    try {
      res = await fetch(`${e.SUPPLIER_API_URL}${path}`, {
        method: init.method ?? "GET",
        headers,
        body: init.body === undefined ? undefined : JSON.stringify(init.body),
        signal: AbortSignal.timeout(init.timeoutMs ?? 20_000),
        cache: "no-store",
        redirect: "error",
      });
      break;
    } catch (error) {
      if (attempt < attempts) {
        await new Promise((resolve) => setTimeout(resolve, 500));
        continue;
      }
      const timedOut = error instanceof Error && (error.name === "TimeoutError" || error.name === "AbortError");
      throw new SupplierError(0, "network_error", timedOut ? "Le fournisseur n'a pas répondu à temps. Réessayez dans un instant." : "Connexion au fournisseur impossible. Réessayez dans un instant.");
    }
  }
  if (!res) throw new SupplierError(0, "network_error", "Connexion au fournisseur impossible.");

  let payload: unknown = null;
  try {
    payload = await res.json();
  } catch {
    // Réponse non JSON : traitée comme une erreur ci-dessous.
  }

  if (!res.ok) {
    const err = (payload as { error?: { code?: unknown; message?: unknown } } | null)?.error;
    const code = typeof err?.code === "string" ? err.code : `http_${res.status}`;
    const message = typeof err?.message === "string" ? err.message : `Erreur fournisseur ${res.status}`;
    throw new SupplierError(res.status, code, message.slice(0, 400), Number(res.headers.get("retry-after")) || 0);
  }
  if (payload === null) throw new SupplierError(res.status, "invalid_response", "Réponse fournisseur illisible");
  return payload;
}

function parse<T>(schema: z.ZodType<T>, value: unknown, what: string): T {
  const result = schema.safeParse(value);
  if (!result.success) throw new SupplierError(200, "invalid_response", `Réponse fournisseur inattendue (${what})`);
  return result.data;
}

/**
 * Catalogue complet (toutes les pages). Les entrées illisibles sont ignorées plutôt que de bloquer la
 * synchronisation, mais leurs identifiants restent dans `seenIds` : un produit présent chez le fournisseur
 * n'est jamais désactivé à cause d'un format inattendu.
 */
export async function listAllProducts(): Promise<{ products: SupplierProduct[]; seenIds: string[]; invalid: number }> {
  const products: SupplierProduct[] = [];
  const seenIds: string[] = [];
  let invalid = 0;
  for (let page = 1; page <= 50; page++) {
    const payload = (await call(`/products?page=${page}&per_page=100`)) as { data?: unknown; pagination?: { has_more?: boolean } };
    if (!Array.isArray(payload.data)) throw new SupplierError(200, "invalid_response", "Réponse fournisseur inattendue (catalogue)");
    for (const raw of payload.data) {
      const rawId = (raw as { id?: unknown } | null)?.id;
      if (typeof rawId === "string" && rawId) seenIds.push(rawId);
      const item = productSchema.safeParse(raw);
      if (item.success) products.push(item.data);
      else invalid++;
    }
    if (!payload.pagination?.has_more) break;
  }
  if (invalid) console.warn(`[catalogue] ${invalid} produit(s) fournisseur illisible(s), ignorés`);
  return { products, seenIds, invalid };
}

export async function getProduct(idOrSlug: string): Promise<SupplierProduct> {
  const payload = (await call(`/products/${encodeURIComponent(idOrSlug)}`)) as { data?: unknown };
  return parse(productSchema, payload.data ?? payload, "produit");
}

export async function getBalance(): Promise<SupplierBalance> {
  return parse(balanceSchema, await call("/balance"), "solde");
}

/**
 * Achat chez le fournisseur (débite le solde). Toujours appelé avec une clé
 * d'idempotence stable : une répétition renvoie la commande d'origine.
 */
export async function createOrder(input: { product: string; quantity: number; emails?: string[]; idempotencyKey: string }): Promise<SupplierOrder> {
  const body: Record<string, unknown> = { product: input.product, quantity: input.quantity };
  if (input.emails?.length) body.emails = input.emails;
  const payload = await call("/orders", { method: "POST", body, idempotencyKey: input.idempotencyKey, timeoutMs: 60_000 });
  return parse(orderSchema, (payload as { data?: unknown }).data ?? payload, "commande");
}

export async function getOrder(id: string): Promise<SupplierOrder> {
  const payload = await call(`/orders/${encodeURIComponent(id)}`);
  return parse(orderSchema, (payload as { data?: unknown }).data ?? payload, "commande");
}

/** Les éléments livrés sont des chaînes ; tout autre format est sérialisé tel quel. */
export function deliveryItems(order: SupplierOrder): string[] {
  return (order.items ?? []).map((item) => (typeof item === "string" ? item : JSON.stringify(item))).filter((s) => s.length > 0);
}

export function usdToCents(amount: number): number {
  return Math.round(amount * 100);
}
