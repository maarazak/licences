import "server-only";
import { and, eq, gte, inArray, isNotNull, lt, lte, ne, or } from "drizzle-orm";
import { affected, db, schema } from "@/server/db";
import type { Order } from "@/server/db/schema";
import type { OrderStatus } from "@/lib/enums";
import { sendMail } from "@/server/mail/mailer";
import { paymentReminderMail } from "@/server/mail/templates";
import { isAvailable } from "./catalog";
import { logEvent, syncPayment } from "./orders";

/**
 * Relances d'un paiement non terminé. Au plus DEUX e-mails par commande :
 *  1. 20 à 50 minutes après la commande, tant que la session de paiement est ouverte ;
 *  2. entre 90 minutes et 24 heures après, quand elle a expiré ou échoué : on propose de recommencer.
 *
 * Garde-fous : jamais pour un compte suspendu ou qui a désactivé les rappels,
 * jamais si le client a déjà recommandé la même offre, jamais plus d'un rappel
 * toutes les 6 heures pour un même client, jamais pour une offre indisponible.
 * La réservation du rappel est atomique : deux exécutions simultanées n'envoient pas deux e-mails.
 */

const MINUTE = 60_000;
const BATCH = 20;
const MIN_GAP_BETWEEN_REMINDERS_MS = 6 * 60 * MINUTE;
/** Statuts qui montrent que le client a donné suite (nouvelle commande de la même offre). */
const FOLLOWED_UP: OrderStatus[] = ["pending_payment", "paid", "processing", "activation_awaiting", "delivered"];
/** Commandes réellement payées : si la même offre a été payée dans les dernières 24 h, aucune relance (risque de double paiement). */
const PAID: OrderStatus[] = ["paid", "processing", "activation_awaiting", "delivered"];

async function remindedRecently(userId: string, exceptOrderId: string): Promise<boolean> {
  const [row] = await db()
    .select({ id: schema.orders.id })
    .from(schema.orders)
    .where(and(eq(schema.orders.userId, userId), ne(schema.orders.id, exceptOrderId), gte(schema.orders.lastReminderAt, new Date(Date.now() - MIN_GAP_BETWEEN_REMINDERS_MS))))
    .limit(1);
  return !!row;
}

async function hasFollowUp(order: Order): Promise<boolean> {
  const [row] = await db()
    .select({ id: schema.orders.id })
    .from(schema.orders)
    .where(
      and(
        eq(schema.orders.userId, order.userId),
        eq(schema.orders.productId, order.productId),
        ne(schema.orders.id, order.id),
        or(
          and(gte(schema.orders.createdAt, order.createdAt), inArray(schema.orders.status, FOLLOWED_UP)),
          and(gte(schema.orders.createdAt, new Date(Date.now() - 24 * 60 * MINUTE)), inArray(schema.orders.status, PAID)),
        ),
      ),
    )
    .limit(1);
  return !!row;
}

async function send(order: Order, email: string, lang: string, slug: string, stage: 1 | 2): Promise<boolean> {
  const previous = order.reminderCount;
  // Réservation atomique : stage 1 seulement si aucun rappel n'a été envoyé, stage 2 si moins de deux.
  const claimed = await db()
    .update(schema.orders)
    .set({ reminderCount: stage, lastReminderAt: new Date() })
    .where(and(eq(schema.orders.id, order.id), stage === 1 ? eq(schema.orders.reminderCount, 0) : lt(schema.orders.reminderCount, 2)));
  if (affected(claimed) !== 1) return false;

  const ok = await sendMail(paymentReminderMail(email, { ...order, slug }, stage, lang === "en" ? "en" : "fr"));
  if (!ok) {
    // L'envoi a échoué : on libère la réservation pour réessayer au prochain passage.
    await db().update(schema.orders).set({ reminderCount: previous, lastReminderAt: null }).where(eq(schema.orders.id, order.id));
    return false;
  }
  await logEvent(order.id, "reminder", `Relance n°${stage} envoyée par e-mail.`);
  return true;
}

export async function processReminders(): Promise<{ stage1: number; stage2: number }> {
  const now = Date.now();
  const report = { stage1: 0, stage2: 0 };
  const eligibleUser = and(eq(schema.users.isBlocked, false), eq(schema.users.mailReminders, true));

  // Étape 1 : session encore ouverte.
  const open = await db()
    .select({ order: schema.orders, email: schema.users.email, lang: schema.users.lang, slug: schema.products.slug })
    .from(schema.orders)
    .innerJoin(schema.users, eq(schema.users.id, schema.orders.userId))
    .innerJoin(schema.products, eq(schema.products.id, schema.orders.productId))
    .where(and(eq(schema.orders.status, "pending_payment"), isNotNull(schema.orders.paymentUrl), eq(schema.orders.reminderCount, 0), gte(schema.orders.createdAt, new Date(now - 50 * MINUTE)), lte(schema.orders.createdAt, new Date(now - 20 * MINUTE)), eligibleUser))
    .limit(BATCH);
  for (const { order, email, lang, slug } of open) {
    // Relecture du paiement : s'il vient d'être confirmé ou a échoué, on ne relance pas.
    const fresh = await syncPayment(order.id);
    if (!fresh || fresh.status !== "pending_payment") continue;
    if (await hasFollowUp(fresh)) continue;
    if (await remindedRecently(order.userId, order.id)) continue;
    if (await send(fresh, email, lang, slug, 1)) report.stage1++;
  }

  // Étape 2 : session expirée ou paiement échoué.
  const closed = await db()
    .select({ order: schema.orders, email: schema.users.email, lang: schema.users.lang, product: schema.products })
    .from(schema.orders)
    .innerJoin(schema.users, eq(schema.users.id, schema.orders.userId))
    .innerJoin(schema.products, eq(schema.products.id, schema.orders.productId))
    .where(and(inArray(schema.orders.status, ["expired", "payment_failed"]), lt(schema.orders.reminderCount, 2), gte(schema.orders.createdAt, new Date(now - 24 * 60 * MINUTE)), lte(schema.orders.createdAt, new Date(now - 90 * MINUTE)), eligibleUser))
    .limit(BATCH);
  for (const { order, email, lang, product } of closed) {
    if (!isAvailable(product)) continue;
    // Relecture pawaPay : un paiement abouti tardivement (webhook perdu) ne doit jamais recevoir « recommencez ».
    const fresh = await syncPayment(order.id);
    if (!fresh || (fresh.status !== "expired" && fresh.status !== "payment_failed")) continue;
    if (await hasFollowUp(fresh)) continue;
    if (await remindedRecently(order.userId, order.id)) continue;
    if (await send(fresh, email, lang, product.slug, 2)) report.stage2++;
  }
  return report;
}
