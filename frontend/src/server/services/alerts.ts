import "server-only";
import { and, asc, desc, eq, gt, isNull, or } from "drizzle-orm";
import { affected, db, schema } from "@/server/db";
import { sendMail } from "@/server/mail/mailer";
import { stockBackMail } from "@/server/mail/templates";
import { newId } from "@/server/security/crypto";
import { isAvailable } from "./catalog";

/** Alertes de retour en stock : un seul e-mail par offre, puis l'alerte s'arrête. */

const MAX_ALERTS_PER_USER = 30;

export async function addAlert(userId: string, productId: string): Promise<"created" | "limit" | "unknown"> {
  const [product] = await db()
    .select({ id: schema.products.id })
    .from(schema.products)
    .where(and(eq(schema.products.id, productId), eq(schema.products.isPublished, true)))
    .limit(1);
  if (!product) return "unknown";
  const active = await db()
    .select({ id: schema.stockAlerts.id })
    .from(schema.stockAlerts)
    .where(and(eq(schema.stockAlerts.userId, userId), isNull(schema.stockAlerts.notifiedAt)));
  if (active.length >= MAX_ALERTS_PER_USER) return "limit";
  const now = new Date();
  // Une alerte déjà envoyée pour cette offre est réarmée plutôt que dupliquée.
  await db()
    .insert(schema.stockAlerts)
    .values({ id: newId(), userId, productId, createdAt: now })
    .onDuplicateKeyUpdate({ set: { notifiedAt: null, createdAt: now } });
  return "created";
}

export async function removeAlert(userId: string, alertId: string): Promise<void> {
  await db()
    .delete(schema.stockAlerts)
    .where(and(eq(schema.stockAlerts.id, alertId), eq(schema.stockAlerts.userId, userId)));
}

export async function listUserAlerts(userId: string) {
  return db()
    .select({ alert: schema.stockAlerts, product: schema.products })
    .from(schema.stockAlerts)
    .innerJoin(schema.products, eq(schema.products.id, schema.stockAlerts.productId))
    .where(and(eq(schema.stockAlerts.userId, userId), isNull(schema.stockAlerts.notifiedAt)))
    .orderBy(desc(schema.stockAlerts.createdAt));
}

export async function hasActiveAlert(userId: string, productId: string): Promise<boolean> {
  const [row] = await db()
    .select({ id: schema.stockAlerts.id })
    .from(schema.stockAlerts)
    .where(and(eq(schema.stockAlerts.userId, userId), eq(schema.stockAlerts.productId, productId), isNull(schema.stockAlerts.notifiedAt)))
    .limit(1);
  return !!row;
}

export async function countActiveAlerts(userId: string): Promise<number> {
  return (await listUserAlerts(userId)).length;
}

/** Envoie les alertes des offres revenues en stock (tâche planifiée). */
export async function processStockAlerts(): Promise<number> {
  const pending = await db()
    .select({ alert: schema.stockAlerts, product: schema.products, email: schema.users.email, lang: schema.users.lang })
    .from(schema.stockAlerts)
    .innerJoin(schema.products, eq(schema.products.id, schema.stockAlerts.productId))
    .innerJoin(schema.users, eq(schema.users.id, schema.stockAlerts.userId))
    .where(
      and(
        isNull(schema.stockAlerts.notifiedAt),
        eq(schema.products.inStock, true),
        eq(schema.products.isPublished, true),
        eq(schema.products.supplierActive, true),
        eq(schema.products.supplierPurchasable, true),
        eq(schema.products.preorder, false),
        or(isNull(schema.products.stock), gt(schema.products.stock, 0)),
      ),
    )
    .orderBy(asc(schema.stockAlerts.createdAt))
    .limit(100);
  let sent = 0;
  for (const { alert, product, email, lang } of pending) {
    if (!isAvailable(product)) continue;
    // Réservation atomique : deux exécutions simultanées n'envoient pas deux e-mails.
    const claimed = await db()
      .update(schema.stockAlerts)
      .set({ notifiedAt: new Date() })
      .where(and(eq(schema.stockAlerts.id, alert.id), isNull(schema.stockAlerts.notifiedAt)));
    if (affected(claimed) !== 1) continue;
    await sendMail(stockBackMail(email, { slug: product.slug, name: product.displayName?.trim() || product.name }, lang === "en" ? "en" : "fr"));
    sent++;
  }
  return sent;
}
