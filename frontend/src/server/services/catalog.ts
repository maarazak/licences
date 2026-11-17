import "server-only";
import { cache } from "react";
import { and, asc, desc, eq, inArray, like, ne, notInArray, or, sql } from "drizzle-orm";
import { db, schema } from "@/server/db";
import type { Product } from "@/server/db/schema";
import { formatDuration } from "@/lib/format";
import { sellPriceXof } from "@/lib/pricing";
import { newId } from "@/server/security/crypto";
import { logoFor } from "@/server/brands";
import type { LogoSpec } from "@/lib/logo";
import { getSettings, loadSettings, saveSettings, type ShopSettings } from "./settings";
import { getBalance, listAllProducts, usdToCents, type SupplierProduct } from "./supplier";

export type CatalogProduct = Product & {
  priceXof: number;
  available: boolean;
  title: string;
  /** Rayon de la boutique (vide si non classé). */
  shelf: string;
  /** Type d'accès brut du fournisseur, à passer à `deliveryKindLabel`. */
  accessKind: string;
  /** Durée lisible (« 1 mois », « Selon l'offre »…). */
  duration: string;
  /** Logo de la marque (pictogramme, fichier local ou monogramme coloré). */
  logo: LogoSpec;
};

/** Un produit est achetable si la boutique ET le fournisseur le permettent. */
export function isAvailable(p: Product): boolean {
  return p.isPublished && p.supplierActive && p.supplierPurchasable && p.inStock && !p.preorder && (p.stock === null || p.stock > 0);
}

export function decorate(p: Product, s: ShopSettings): CatalogProduct {
  return {
    ...p,
    priceXof: sellPriceXof(p, s),
    available: isAvailable(p),
    title: p.displayName?.trim() || p.name,
    shelf: p.shopCategory?.trim() ?? "",
    // Le champ `category` du fournisseur ne sert que s'il décrit bien un type d'accès.
    accessKind: /account|link|code/i.test(p.category) ? p.category : p.deliveryKind,
    duration: p.durationLabel?.trim() || formatDuration(p.expireDays),
    logo: logoFor(p.name, p.displayName),
  };
}

function slugify(value: string): string {
  return (
    value
      .normalize("NFD")
      .replace(/[̀-ͯ]/g, "")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "")
      .slice(0, 150) || "produit"
  );
}

/** Champs issus du fournisseur. Les réglages boutique (publication, prix fixe, description) ne sont jamais touchés. */
function supplierFields(p: SupplierProduct, now: Date) {
  return {
    name: p.name.slice(0, 255),
    category: (p.category ?? "").slice(0, 100),
    imageUrl: p.image_url && /^https:\/\//.test(p.image_url) && p.image_url.length <= 1024 ? p.image_url : null,
    deliveryKind: (p.delivery_kind ?? "").slice(0, 64),
    supplierDescription: p.description?.trim() ? p.description.trim().slice(0, 20_000) : null,
    costUsdCents: usdToCents(p.price),
    listUsdCents: usdToCents(p.list_price ?? p.price),
    stock: p.stock ?? null,
    inStock: p.in_stock,
    preorder: p.preorder,
    expireDays: p.expire_days ?? 0,
    warranty: (p.warranty ?? "").slice(0, 100),
    requiresEmail: p.requires_email,
    supplierPurchasable: p.purchasable,
    supplierActive: true,
    syncedAt: now,
    updatedAt: now,
  };
}

type SupplierFields = ReturnType<typeof supplierFields>;

/** Nombre de lectures consécutives nécessaires pour enregistrer qu'un produit vendable est devenu indisponible. */
export const CONFIRM_UNAVAILABLE_READINGS = 5;

const AVAILABILITY_KEYS = ["stock", "inStock", "preorder", "supplierPurchasable"] as const;

function sellableState(f: { supplierPurchasable: boolean; inStock: boolean; preorder: boolean; stock: number | null }): boolean {
  return f.supplierPurchasable && f.inStock && !f.preorder && (f.stock === null || f.stock > 0);
}

/**
 * Décide ce qu'il faut écrire pour une nouvelle lecture d'un produit déjà connu. Le fournisseur annonce parfois, pendant
 * quelques minutes, « stock 0 / hors stock / non vendable » pour de nombreux produits, puis revient à la normale :
 * - un produit vendable annoncé indisponible garde son ancienne disponibilité (prix et textes sont mis à jour) tant que
 *   la dégradation n'est pas confirmée par `CONFIRM_UNAVAILABLE_READINGS` lectures consécutives ;
 * - un retour à la disponibilité, ou toute autre évolution, est enregistré tout de suite ;
 * - `immediate` (coût nul : jamais vendable) applique sans attendre.
 * Retourne null si rien ne change.
 */
function applyReading(known: Product, fields: SupplierFields, immediate = false): { set: SupplierFields & { unavailableReadings: number }; held: boolean } | null {
  const degraded = !immediate && sellableState(known) && !sellableState(fields);
  let set: SupplierFields & { unavailableReadings: number };
  let held = false;
  if (degraded && known.unavailableReadings + 1 < CONFIRM_UNAVAILABLE_READINGS) {
    held = true;
    const keep = Object.fromEntries(AVAILABILITY_KEYS.map((key) => [key, known[key]])) as Pick<Product, (typeof AVAILABILITY_KEYS)[number]>;
    set = { ...fields, ...keep, unavailableReadings: known.unavailableReadings + 1 };
  } else {
    set = { ...fields, unavailableReadings: 0 };
  }
  const unchanged = TRACKED.every((key) => known[key] === set[key]) && known.unavailableReadings === set.unavailableReadings;
  return unchanged ? null : { set, held };
}

/** Met à jour un produit à partir de sa fiche fournisseur en direct (avant un achat), avec la même confirmation des dégradations. */
export async function refreshProduct(known: Product, live: SupplierProduct): Promise<void> {
  const now = new Date();
  const change = applyReading(known, live.price > 0 ? supplierFields(live, now) : { ...supplierFields(live, now), inStock: false, supplierPurchasable: false }, live.price <= 0);
  if (change) await db().update(schema.products).set(change.set).where(eq(schema.products.id, known.id));
}

export async function markSupplierInactive(productId: string): Promise<void> {
  await db().update(schema.products).set({ supplierActive: false, updatedAt: new Date() }).where(eq(schema.products.id, productId));
}

export type SyncReport = { total: number; created: number; updated: number; unchanged: number; deactivated: number; /** Dégradations de disponibilité en attente de confirmation. */ held: number };

/** Champs fournisseur comparés pour savoir si un produit a changé (prix, stock, état, textes…). */
const TRACKED = ["name", "category", "imageUrl", "deliveryKind", "supplierDescription", "costUsdCents", "listUsdCents", "stock", "inStock", "preorder", "expireDays", "warranty", "requiresEmail", "supplierPurchasable", "supplierActive"] as const;

/**
 * Synchronise tout le catalogue fournisseur vers NOTRE base : la boutique ne lit jamais le fournisseur pour afficher.
 * Seuls les produits dont un champ a réellement changé (prix, stock, état…) sont réécrits. Un produit absent de la
 * liste n'est plus en vente. Les réglages boutique (publication, prix fixe, descriptions) ne sont jamais touchés.
 */
export async function syncCatalog(): Promise<SyncReport> {
  const { products: remote, seenIds } = await listAllProducts();
  const settings = await loadSettings();
  const now = new Date();
  const existing = await db().select().from(schema.products);
  const bySupplierId = new Map(existing.map((p) => [p.supplierId, p]));
  const slugs = new Set(existing.map((p) => p.slug));
  const report: SyncReport = { total: remote.length, created: 0, updated: 0, unchanged: 0, deactivated: 0, held: 0 };

  for (const item of remote) {
    const sellable = item.price > 0; // un coût nul ou négatif n'est jamais vendable
    const known = bySupplierId.get(item.id);
    // Un produit connu passé à un coût nul est mis à jour ET retiré de la vente (jamais laissé « disponible » à l'ancien prix).
    const fields = sellable ? supplierFields(item, now) : { ...supplierFields(item, now), inStock: false, supplierPurchasable: false };
    if (known) {
      const change = applyReading(known, fields, !sellable);
      if (!change) {
        report.unchanged++;
        continue;
      }
      await db().update(schema.products).set(change.set).where(eq(schema.products.id, known.id));
      if (change.held) report.held++;
      report.updated++;
      continue;
    }
    if (!sellable) continue;
    let slug = slugify(item.slug || item.name);
    if (slugs.has(slug)) slug = `${slug}-${slugify(item.id).slice(-8)}`;
    if (slugs.has(slug)) slug = `${slug}-${newId().slice(0, 8)}`;
    slugs.add(slug);
    await db()
      .insert(schema.products)
      .values({ id: newId(), supplierId: item.id, slug, ...fields, isPublished: settings.autoPublish, createdAt: now })
      // Deux synchronisations simultanées (tâche planifiée + bouton) : la seconde met à jour au lieu d'échouer.
      .onDuplicateKeyUpdate({ set: fields });
    report.created++;
  }

  // Une seule requête pour la date de dernière vérification des produits inchangés.
  if (seenIds.length > 0) await db().update(schema.products).set({ syncedAt: now }).where(inArray(schema.products.supplierId, seenIds));

  // Garde-fou : une liste vide ne désactive pas tout le catalogue (panne probable côté fournisseur).
  if (seenIds.length > 0) {
    const result = await db()
      .update(schema.products)
      .set({ supplierActive: false, updatedAt: now })
      .where(and(notInArray(schema.products.supplierId, seenIds), eq(schema.products.supplierActive, true)));
    report.deactivated = Number((Array.isArray(result) ? result[0] : result)?.affectedRows ?? 0);
  }
  await saveSettings({ lastCatalogSyncAt: now.toISOString(), catalogSyncError: "" });
  return report;
}

/** Relit le solde chez le fournisseur et le mémorise (tâche planifiée et bouton de synchronisation, jamais à l'affichage d'une page). */
export async function refreshSupplierBalance(): Promise<void> {
  try {
    await saveSettings({ supplierBalanceCents: usdToCents((await getBalance()).balance), supplierBalanceAt: new Date().toISOString(), supplierBalanceError: "" });
  } catch (error) {
    await saveSettings({ supplierBalanceError: (error instanceof Error ? error.message : "Solde illisible").slice(0, 300) });
    throw error;
  }
}

/** Vrai si la dernière synchronisation du catalogue date de plus de 5 minutes (la tâche planifiée ne tourne probablement pas). */
export function isCatalogSyncStale(lastSyncIso: string): boolean {
  const last = Date.parse(lastSyncIso);
  return !Number.isFinite(last) || Date.now() - last > 5 * 60_000;
}

export type CatalogFilters = { q?: string; category?: string; availability?: "all" | "available"; sort?: "featured" | "price_asc" | "price_desc" | "name" };

const visible = () => and(eq(schema.products.isPublished, true), eq(schema.products.supplierActive, true));

/** Catalogue public. Le tri par prix se fait en mémoire car le prix est calculé. */
export async function listCatalog(filters: CatalogFilters = {}): Promise<CatalogProduct[]> {
  const settings = await getSettings();
  const conditions = [visible()];
  const q = filters.q?.trim().slice(0, 80);
  if (q) {
    const pattern = `%${q.replace(/[\\%_]/g, (c) => `\\${c}`)}%`;
    conditions.push(or(like(schema.products.name, pattern), like(schema.products.displayName, pattern), like(schema.products.shopCategory, pattern)));
  }
  if (filters.category) conditions.push(eq(schema.products.shopCategory, filters.category));
  const rows = await db()
    .select()
    .from(schema.products)
    .where(and(...conditions))
    .orderBy(sql`COALESCE(${schema.products.featuredRank}, 9999)`, asc(schema.products.name))
    .limit(500);
  let list = rows.map((p) => decorate(p, settings));
  if (filters.availability === "available") list = list.filter((p) => p.available);
  if (filters.sort === "price_asc") list.sort((a, b) => a.priceXof - b.priceXof);
  else if (filters.sort === "price_desc") list.sort((a, b) => b.priceXof - a.priceXof);
  else if (filters.sort === "name") list.sort((a, b) => a.title.localeCompare(b.title, "fr"));
  else list.sort((a, b) => Number(b.available) - Number(a.available) || (a.featuredRank ?? 9999) - (b.featuredRank ?? 9999));
  return list;
}

/** Noms des produits en vente, pour la recherche de l'en-tête (liste légère, mémorisée pour la durée d'une requête). */
export const listSearchItems = cache(async (): Promise<Array<{ slug: string; title: string; shelf: string }>> => {
  const rows = await db()
    .select({ slug: schema.products.slug, name: schema.products.name, displayName: schema.products.displayName, shelf: schema.products.shopCategory })
    .from(schema.products)
    .where(visible())
    .orderBy(asc(schema.products.name))
    .limit(500);
  return rows.map((r) => ({ slug: r.slug, title: r.displayName?.trim() || r.name, shelf: r.shelf?.trim() ?? "" })).sort((a, b) => a.title.localeCompare(b.title, "fr"));
});

/** Produits phares disponibles, dans l'ordre choisi dans le backoffice. */
export async function listFeatured(limit = 8): Promise<CatalogProduct[]> {
  const settings = await getSettings();
  const rows = await db()
    .select()
    .from(schema.products)
    .where(and(visible(), eq(schema.products.isFeatured, true)))
    .orderBy(sql`COALESCE(${schema.products.featuredRank}, 9999)`, asc(schema.products.name))
    .limit(50);
  return rows
    .map((p) => decorate(p, settings))
    .filter((p) => p.available)
    .slice(0, limit);
}

/** Autres formules de la même gamme (même premier mot du nom), pour la fiche produit. */
export async function listRelated(product: CatalogProduct, limit = 4): Promise<CatalogProduct[]> {
  const token = product.name.trim().split(/\s+/)[0] ?? "";
  if (token.length < 3) return [];
  const pattern = `${token.replace(/[\\%_]/g, (c) => `\\${c}`)}%`;
  const rows = await db()
    .select()
    .from(schema.products)
    .where(and(visible(), like(schema.products.name, pattern), ne(schema.products.id, product.id)))
    .orderBy(asc(schema.products.name))
    .limit(20);
  const settings = await getSettings();
  return rows
    .map((p) => decorate(p, settings))
    .sort((a, b) => Number(b.available) - Number(a.available))
    .slice(0, limit);
}

export async function listCategories(): Promise<string[]> {
  const rows = await db().selectDistinct({ category: schema.products.shopCategory }).from(schema.products).where(visible()).orderBy(asc(schema.products.shopCategory));
  return rows.map((r) => r.category?.trim() ?? "").filter(Boolean);
}

export async function getCatalogProduct(slug: string): Promise<CatalogProduct | null> {
  if (!slug || slug.length > 191) return null;
  const [row] = await db()
    .select()
    .from(schema.products)
    .where(and(eq(schema.products.slug, slug), visible()))
    .limit(1);
  return row ? decorate(row, await getSettings()) : null;
}

// ─── Backoffice ──────────────────────────────────────────────────────────────

export async function adminListProducts(q?: string): Promise<CatalogProduct[]> {
  const settings = await getSettings();
  const term = q?.trim().slice(0, 80);
  const pattern = term ? `%${term.replace(/[\\%_]/g, (c) => `\\${c}`)}%` : null;
  const rows = await db()
    .select()
    .from(schema.products)
    .where(pattern ? or(like(schema.products.name, pattern), like(schema.products.displayName, pattern), like(schema.products.shopCategory, pattern), like(schema.products.slug, pattern)) : undefined)
    .orderBy(desc(schema.products.supplierActive), desc(schema.products.isPublished), asc(schema.products.name))
    .limit(1000);
  return rows.map((p) => decorate(p, settings));
}

export async function adminGetProduct(id: string): Promise<CatalogProduct | null> {
  const [row] = await db().select().from(schema.products).where(eq(schema.products.id, id)).limit(1);
  return row ? decorate(row, await getSettings()) : null;
}

export async function setPublished(ids: string[], published: boolean): Promise<void> {
  if (!ids.length) return;
  await db().update(schema.products).set({ isPublished: published, updatedAt: new Date() }).where(inArray(schema.products.id, ids));
}

export async function publishAllSellable(): Promise<number> {
  const result = await db()
    .update(schema.products)
    .set({ isPublished: true, updatedAt: new Date() })
    .where(and(eq(schema.products.supplierActive, true), eq(schema.products.supplierPurchasable, true), eq(schema.products.preorder, false), eq(schema.products.isPublished, false)));
  return Number((Array.isArray(result) ? result[0] : result)?.affectedRows ?? 0);
}

export type ProductEdit = {
  isPublished: boolean;
  isFeatured: boolean;
  displayName: string | null;
  shopCategory: string | null;
  durationLabel: string | null;
  featuredRank: number | null;
  description: string | null;
  descriptionEn: string | null;
  instructions: string | null;
  instructionsEn: string | null;
  priceXofOverride: number | null;
  marginPercentOverride: number | null;
};

export async function updateProductSettings(id: string, edit: ProductEdit): Promise<void> {
  await db()
    .update(schema.products)
    .set({ ...edit, updatedAt: new Date() })
    .where(eq(schema.products.id, id));
}

export async function catalogCounts(): Promise<{ total: number; published: number; sellable: number }> {
  const [row] = await db()
    .select({
      total: sql<number>`COUNT(*)`,
      published: sql<number>`COALESCE(SUM(${schema.products.isPublished} = 1 AND ${schema.products.supplierActive} = 1), 0)`,
      sellable: sql<number>`COALESCE(SUM(${schema.products.supplierActive} = 1 AND ${schema.products.supplierPurchasable} = 1), 0)`,
    })
    .from(schema.products);
  return { total: Number(row?.total ?? 0), published: Number(row?.published ?? 0), sellable: Number(row?.sellable ?? 0) };
}
