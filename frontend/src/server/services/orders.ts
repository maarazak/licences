import "server-only";
import { and, desc, eq, gt, inArray, isNull, lt, or, sql } from "drizzle-orm";
import { affected, db, schema, type Tx } from "@/server/db";
import type { Order, User } from "@/server/db/schema";
import type { OrderStatus } from "@/lib/enums";
import type { Lang } from "@/lib/i18n";
import { MAX_QUANTITY } from "@/lib/enums";
import { costXof, coversCost, sellPriceXof } from "@/lib/pricing";
import { appUrl } from "@/server/env";
import { staffEmails } from "@/server/services/staff";
import { sendMail } from "@/server/mail/mailer";
import { adminAlertMail, orderActivationMail, orderCreditedMail, orderDelayedMail, orderDeliveredMail, orderPaidMail, orderRefundedMail } from "@/server/mail/templates";
import { decrypt, encrypt, newId, randomReference } from "@/server/security/crypto";
import { releaseRateLimit, rateLimit } from "@/server/security/rate-limit";
import { isAvailable, markSupplierInactive, refreshProduct } from "./catalog";
import { createPaymentPage, getDeposit, isPaymentUrlValid } from "./pawapay";
import { loadSettings } from "./settings";
import { creditWallet, debitWallet, InsufficientBalanceError } from "./wallet";
import { createOrder as supplierCreateOrder, deliveryItems, getOrder as supplierGetOrder, getProduct, SupplierError, usdToCents, type SupplierOrder } from "./supplier";

/**
 * Cycle de vie d'une commande :
 *
 *   pending_payment ──paiement confirmé par pawaPay──▶ paid ──▶ processing ──▶ delivered
 *        │                                                        │
 *        ├─▶ payment_failed / expired / cancelled                 ├─▶ activation_awaiting ──▶ delivered
 *                                                                 └─▶ failed (payée, non livrée : équipe) ──▶ refunded
 *
 * Chaque transition est un UPDATE conditionnel sur le statut de départ : deux
 * traitements concurrents (webhook, retour client, tâche planifiée) ne peuvent
 * pas appliquer la même transition deux fois.
 */

const MAX_FULFIL_ATTEMPTS = 12;
const LOCK_MS = 3 * 60_000;

export class CheckoutError extends Error {}

export async function logEvent(orderId: string, type: string, message: string, actor = "system"): Promise<void> {
  await db()
    .insert(schema.orderEvents)
    .values({ id: newId(), orderId, type, message: message.slice(0, 500), actor: actor.slice(0, 191), createdAt: new Date() });
}

async function load(orderId: string): Promise<Order | null> {
  const [order] = await db().select().from(schema.orders).where(eq(schema.orders.id, orderId)).limit(1);
  return order ?? null;
}

/** Adresse et langue du client (les e-mails partent dans sa langue). */
async function owner(order: Order): Promise<{ email: string; lang: Lang } | null> {
  const [u] = await db().select({ email: schema.users.email, lang: schema.users.lang }).from(schema.users).where(eq(schema.users.id, order.userId)).limit(1);
  return u ? { email: u.email, lang: u.lang === "en" ? "en" : "fr" } : null;
}

async function alertAdmins(subject: string, lines: string[], path: string): Promise<void> {
  // Une même alerte (même objet, même première ligne) n'est envoyée qu'une fois par heure :
  // un client ne peut pas inonder les administrateurs en répétant une commande refusée.
  const gate = await rateLimit("admin-alert", `${subject}|${lines[0] ?? ""}`, 1, 3600);
  if (!gate.ok) return;
  const to = await staffEmails();
  const results = await Promise.all(to.map((address) => sendMail(adminAlertMail(address, subject, lines, path))));
  // Aucun e-mail parti (SMTP en panne, aucun destinataire) : on libère la limite pour que l'alerte puisse être renvoyée.
  if (!results.some(Boolean)) await releaseRateLimit("admin-alert", `${subject}|${lines[0] ?? ""}`);
}

/** Transition atomique : ne s'applique que si la commande est encore dans l'un des statuts de départ. */
async function transition(orderId: string, from: OrderStatus[], patch: Partial<Order>, exec: Tx | ReturnType<typeof db> = db()): Promise<boolean> {
  const result = await exec
    .update(schema.orders)
    .set({ ...patch, updatedAt: new Date() })
    .where(and(eq(schema.orders.id, orderId), inArray(schema.orders.status, from)));
  return affected(result) === 1;
}

// ─── Création de commande et session de paiement ─────────────────────────────

export type CheckoutInput = {
  productId: string;
  quantity: number;
  fullName?: string | null;
  firstName?: string | null;
  lastName?: string | null;
  phone?: string | null;
  country?: string | null;
  activationEmails: string[];
  /** Payer la commande entière avec l'avoir du client. */
  payWithBalance: boolean;
  /** Total affiché au client au moment de valider : refusé si le prix a changé depuis. */
  expectedTotalXof: number;
  /** Jeton généré par le formulaire : deux envois avec le même jeton ne créent qu'une commande. */
  clientToken?: string;
};

/**
 * Crée la commande puis la session de paiement. Avant d'encaisser, on vérifie
 * en direct chez le fournisseur que le produit est livrable, que le prix
 * couvre le coût et que notre solde suffit : on ne prend pas l'argent d'un
 * client pour une commande qu'on ne peut pas honorer.
 */
export async function startCheckout(user: User, input: CheckoutInput): Promise<{ orderId: string; paymentUrl: string | null }> {
  if (!Number.isInteger(input.quantity) || input.quantity < 1 || input.quantity > MAX_QUANTITY) throw new CheckoutError("Quantité invalide.");

  const [product] = await db().select().from(schema.products).where(eq(schema.products.id, input.productId)).limit(1);
  if (!product || !isAvailable(product)) throw new CheckoutError("Cette offre n'est plus disponible.");

  const unavailable = new CheckoutError("Cette offre est momentanément indisponible. Réessayez plus tard.");
  let live;
  try {
    live = await getProduct(product.supplierId);
  } catch (error) {
    if (error instanceof SupplierError && (error.code === "product_not_found" || error.code === "product_inactive")) {
      await markSupplierInactive(product.id);
      throw new CheckoutError("Cette offre n'est plus disponible.");
    }
    console.error("[checkout] fournisseur injoignable", error);
    throw unavailable;
  }
  await refreshProduct(product, live);
  if (!live.purchasable || !live.in_stock || live.preorder || (live.stock != null && live.stock < input.quantity)) {
    throw new CheckoutError(live.stock != null && live.stock > 0 ? `Il ne reste que ${live.stock} unité(s) pour cette offre.` : "Cette offre est en rupture de stock.");
  }

  const emails = live.requires_email ? input.activationEmails : [];
  if (live.requires_email && emails.length !== input.quantity) throw new CheckoutError("Indiquez une adresse e-mail d'activation par unité achetée.");

  const settings = await loadSettings();
  const costUsdCents = usdToCents(live.price);
  const unitPriceXof = sellPriceXof({ ...product, costUsdCents }, settings);
  if (costUsdCents <= 0 || !coversCost(unitPriceXof, costUsdCents, settings.usdRate)) {
    await alertAdmins("Vente bloquée : prix inférieur au coût", [`${product.name} : prix ${unitPriceXof} FCFA, coût ${costXof(costUsdCents, settings.usdRate)} FCFA.`, "Corrigez le prix fixe, la marge ou le taux USD."], `/admin/produits/${product.id}`);
    throw unavailable;
  }

  // Double clic / renvoi du même formulaire : on retrouve la commande déjà créée au lieu d'en créer une seconde.
  if (input.clientToken) {
    const existing = await findByClientToken(user.id, input.clientToken);
    if (existing) return { orderId: existing.id, paymentUrl: existing.status === "pending_payment" ? existing.paymentUrl : null };
  }

  let firstName = (input.firstName || "").trim();
  let lastName = (input.lastName || "").trim();
  if (input.fullName && (!firstName || !lastName)) {
    const trimmed = input.fullName.trim();
    const spaceIdx = trimmed.indexOf(" ");
    if (spaceIdx !== -1) {
      firstName = trimmed.slice(0, spaceIdx).trim();
      lastName = trimmed.slice(spaceIdx + 1).trim();
    } else {
      firstName = trimmed;
      lastName = "";
    }
  }
  if (!firstName) firstName = user.firstName || user.email.split("@")[0] || "Client";
  if (!lastName) lastName = user.lastName || "";
  const phone = input.phone?.trim() || user.phone || null;
  const country = input.country?.trim() || user.country || null;

  const now = new Date();
  const orderId = newId();
  const totalXof = unitPriceXof * input.quantity;
  if (totalXof !== input.expectedTotalXof) throw new CheckoutError("Le prix de cette offre a changé. Vérifiez le nouveau total puis validez à nouveau.");
  const productName = (product.displayName?.trim() || product.name).slice(0, 255);
  let reference = "";
  for (let attempt = 0; ; attempt++) {
    reference = randomReference("CMD");
    const values = {
      id: orderId,
      reference,
      clientToken: input.clientToken ?? null,
      userId: user.id,
      productId: product.id,
      productName,
      supplierProductId: product.supplierId,
      quantity: input.quantity,
      unitPriceXof,
      totalXof,
      unitCostUsdCents: costUsdCents,
      usdRate: settings.usdRate,
      warranty: (live.warranty ?? "").slice(0, 100),
      expireDays: live.expire_days ?? 0,
      customerFirstName: firstName.slice(0, 100),
      customerLastName: lastName.slice(0, 100),
      customerPhone: phone ? phone.slice(0, 32) : null,
      customerCountry: country ? country.slice(0, 2) : null,
      activationEmails: emails.length ? emails : null,
      supplierIdemKey: newId(),
      createdAt: now,
      updatedAt: now,
    };
    try {
      if (input.payWithBalance) {
        // Débit de l'avoir et création de la commande payée : tout ou rien.
        await db().transaction(async (tx) => {
          await debitWallet(tx, { userId: user.id, amountXof: totalXof, kind: "purchase", label: `Achat ${productName} — ${reference}`, orderId, actor: user.email });
          await tx.insert(schema.orders).values({ ...values, status: "paid", paidWith: "balance", paidAt: now });
        });
      } else {
        await db().insert(schema.orders).values({ ...values, status: "pending_payment" });
      }
      break;
    } catch (error) {
      if (error instanceof InsufficientBalanceError) throw new CheckoutError("Votre solde ne couvre pas cette commande.");
      if ((error as { code?: string }).code === "ER_DUP_ENTRY") {
        // Même jeton envoyé deux fois en parallèle : l'autre envoi a gagné, on renvoie sa commande (le débit de celui-ci est annulé).
        if (/client_token/.test(String((error as { message?: string }).message)) && input.clientToken) {
          const existing = await findByClientToken(user.id, input.clientToken);
          if (existing) return { orderId: existing.id, paymentUrl: existing.status === "pending_payment" ? existing.paymentUrl : null };
        }
        // Collision (rarissime) sur la référence : on en tire une autre.
        if (attempt < 3) continue;
      }
      throw error;
    }
  }
  await logEvent(orderId, "created", `Commande créée : ${totalXof} FCFA.`, user.email);

  if (input.payWithBalance) {
    // Le solde est déjà débité et la commande payée : une erreur ici ne doit jamais faire croire au client
    // qu'il n'a rien payé. La tâche planifiée reprend la livraison.
    try {
      await logEvent(orderId, "paid", "Payée avec le solde du client.", user.email);
      await sendMail(orderPaidMail(user.email, { id: orderId, reference, productName, quantity: input.quantity, totalXof }, user.lang === "en" ? "en" : "fr"));
      await fulfil(orderId);
    } catch (error) {
      console.error("[checkout] suite du paiement par solde en échec, reprise par la tâche planifiée", error);
    }
    return { orderId, paymentUrl: null };
  }

  try {
    const checkout = await createPaymentPage({
      depositId: orderId,
      orderId,
      reference,
      amountXof: totalXof,
      description: `${product.name} × ${input.quantity} — commande ${reference}`,
      customer: {
        email: user.email,
        firstName,
        lastName,
        country: country ?? undefined,
        phone: phone ?? undefined,
      },
      returnUrl: appUrl(`/paiement/retour?commande=${orderId}`),
      lang: user.lang === "en" ? "en" : "fr",
    });
    await db()
      .update(schema.orders)
      .set({ paymentCheckoutId: checkout.depositId, paymentIntentId: checkout.depositId, paymentUrl: checkout.redirectUrl, updatedAt: new Date() })
      .where(eq(schema.orders.id, orderId));
    await logEvent(orderId, "checkout_created", "Session de paiement pawaPay créée.");
    return { orderId, paymentUrl: checkout.redirectUrl };
  } catch (error) {
    const rawMessage = error instanceof Error ? error.message : "Erreur inattendue";
    // Sécurité : on nettoie les éventuels paramètres d'URL ou requêtes pour ne jamais exposer de tokens
    const safeMessage = rawMessage.split("?")[0].slice(0, 300);
    await transition(orderId, ["pending_payment"], { status: "cancelled", clientToken: null, failureCode: "checkout_failed", failureMessage: safeMessage });
    await logEvent(orderId, "checkout_failed", `Création du paiement impossible : ${safeMessage}`);
    console.error("[checkout] création de session impossible", error);
    throw new CheckoutError("Le paiement n'a pas pu être initialisé. Aucun montant n'a été débité. Réessayez dans un instant.");
  }
}

async function findByClientToken(userId: string, token: string): Promise<Order | null> {
  const [order] = await db()
    .select()
    .from(schema.orders)
    .where(and(eq(schema.orders.userId, userId), eq(schema.orders.clientToken, token)))
    .limit(1);
  return order ?? null;
}

// ─── Confirmation du paiement ────────────────────────────────────────────────

/** Statuts dans lesquels un paiement peut encore être confirmé (il peut aboutir APRÈS la clôture de la commande). */
const PAYABLE: OrderStatus[] = ["pending_payment", "expired", "payment_failed"];

/**
 * Relit la session auprès de pawaPay et applique le résultat. C'est le seul endroit
 * où une commande passe à « payée ». Une commande déjà close (expirée, paiement
 * échoué) est relue aussi : un paiement mobile money peut aboutir après
 * l'expiration de la session, et le client ne doit jamais être débité sans être livré.
 */
export async function syncPayment(orderId: string): Promise<Order | null> {
  return (await syncPaymentRead(orderId)).order;
}

/** Comme `syncPayment`, mais indique si pawaPay a réellement répondu (`read`) : sans réponse, rien n'est prouvé. */
async function syncPaymentRead(orderId: string): Promise<{ order: Order | null; read: boolean }> {
  const order = await load(orderId);
  if (!order || !PAYABLE.includes(order.status) || !order.paymentCheckoutId) return { order, read: false };
  const probe = { read: true };
  return { order: await syncPaymentOf(order, probe), read: probe.read };
}

async function syncPaymentOf(order: Order, probe: { read: boolean }): Promise<Order | null> {
  const depositId = order.paymentCheckoutId || order.paymentIntentId;
  if (!depositId) return order;
  const open = order.status === "pending_payment";

  let state;
  try {
    state = await getDeposit(depositId);
  } catch (error) {
    console.error("[paiement] lecture pawaPay impossible", order.reference, error);
    probe.read = false;
    return order;
  }
  // La session relue doit correspondre à l'identifiant enregistré pour cette commande.
  if (state.depositId !== depositId) {
    console.error("[paiement] identifiant depositId incohérent", order.reference);
    return order;
  }

  if (state.paymentStatus === "completed") {
    const amountOk =
      state.amount === order.totalXof &&
      (state.currency === "" ||
        state.currency.toUpperCase() === "XOF" ||
        state.currency.toUpperCase() === "XAF");
    if (!amountOk) {
      // Payé, mais pas le bon montant : on ne livre pas, l'équipe tranche.
      const moved = await transition(order.id, PAYABLE, {
        status: "failed",
        paidAt: new Date(),
        failureCode: "amount_mismatch",
        failureMessage: `Montant reçu ${state.amount ?? "?"} ${state.currency}, attendu ${order.totalXof} FCFA.`,
      });
      if (moved) {
        await logEvent(order.id, "amount_mismatch", `Montant payé différent du montant attendu (${state.amount ?? "?"} ${state.currency}).`);
        await alertAdmins(
          "Paiement au montant inattendu",
          [`Commande ${order.reference} : reçu ${state.amount ?? "?"} ${state.currency}, attendu ${order.totalXof} FCFA.`],
          `/admin/commandes/${order.id}`,
        );
      }
      return load(order.id);
    }
    const updateData: Partial<typeof schema.orders.$inferInsert> = {
      status: "paid",
      paidAt: new Date(),
    };
    if (state.providerTransactionId) {
      updateData.paymentIntentId = state.providerTransactionId;
    }
    const moved = await transition(order.id, PAYABLE, updateData);
    if (moved) {
      await logEvent(
        order.id,
        "paid",
        open
          ? "Paiement confirmé par pawaPay."
          : "Paiement confirmé par pawaPay après la clôture de la commande (reçu tardif).",
      );
      if (!open) {
        await alertAdmins(
          "Paiement reçu après la clôture d'une commande",
          [`Commande ${order.reference} (${order.status}) : le paiement a abouti tardivement, la livraison est lancée.`],
          `/admin/commandes/${order.id}`,
        );
      }
      const who = await owner(order);
      if (who) await sendMail(orderPaidMail(who.email, order, who.lang));
      await fulfil(order.id);
    }
    return load(order.id);
  }

  // Commande déjà close : seul un paiement confirmé change quelque chose.
  if (!open) return order;

  // Statut d'échec ou d'expiration chez pawaPay
  if (state.paymentStatus === "failed" || state.paymentStatus === "expired") {
    const status: OrderStatus = state.paymentStatus === "failed" ? "payment_failed" : "expired";
    if (await transition(order.id, ["pending_payment"], { status, failureCode: state.failureCode ?? status, failureMessage: state.failureMessage?.slice(0, 500) })) {
      await logEvent(order.id, status, status === "expired" ? "Session de paiement expirée." : `Paiement refusé ou annulé (${state.failureCode ?? state.status}).`);
    }
    return load(order.id);
  }

  return order;
}

/** Version limitée pour les pages : au plus 6 relectures par minute et par commande (quota d'API partagé). */
export async function syncPaymentThrottled(orderId: string): Promise<Order | null> {
  const gate = await rateLimit("sync-payment", orderId, 6, 60);
  return gate.ok ? syncPayment(orderId) : load(orderId);
}

/** Recherche la commande visée par une notification pawaPay (identifiants de paiement uniquement). */
export async function findOrderByPayment(ids: { paymentId?: string; checkoutId?: string }): Promise<Order | null> {
  const conditions = [];
  if (ids.paymentId) conditions.push(eq(schema.orders.paymentIntentId, ids.paymentId));
  if (ids.checkoutId) conditions.push(eq(schema.orders.paymentCheckoutId, ids.checkoutId));
  if (!conditions.length) return null;
  const [order] = await db()
    .select()
    .from(schema.orders)
    .where(or(...conditions))
    .limit(1);
  return order ?? null;
}

// ─── Livraison (achat chez le fournisseur) ───────────────────────────────────

/**
 * Lance ou reprend l'achat chez le fournisseur. La prise de verrou est
 * atomique ; la clé d'idempotence est stable tant que l'issue est incertaine,
 * donc une reprise ne peut pas acheter deux fois.
 */
export async function fulfil(orderId: string): Promise<void> {
  const now = new Date();
  const claimed = await db()
    .update(schema.orders)
    .set({ status: "processing", fulfilLockedAt: now, fulfilAttempts: sql`${schema.orders.fulfilAttempts} + 1`, updatedAt: now })
    .where(
      and(
        eq(schema.orders.id, orderId),
        or(eq(schema.orders.status, "paid"), and(eq(schema.orders.status, "processing"), or(isNull(schema.orders.fulfilLockedAt), lt(schema.orders.fulfilLockedAt, new Date(now.getTime() - LOCK_MS))))),
      ),
    );
  if (affected(claimed) !== 1) return;

  const order = await load(orderId);
  if (!order) return;

  // Premier essai seulement (aucun appel incertain avant) : on ne vend pas à perte si le coût a flambé
  // pendant la fenêtre de paiement. Une hausse absorbée par la marge ne bloque rien.
  if (order.fulfilAttempts <= 1) {
    try {
      const live = await getProduct(order.supplierProductId);
      const liveCents = usdToCents(live.price);
      if (liveCents > order.unitCostUsdCents && costXof(liveCents * order.quantity, order.usdRate) > order.totalXof) {
        await markFailed(order, "price_changed", `Coût fournisseur passé de ${order.unitCostUsdCents} à ${liveCents} cents entre la commande et la livraison : vente à perte évitée.`);
        return;
      }
    } catch (error) {
      console.error("[livraison] contrôle du coût impossible", order.reference, error instanceof Error ? error.message : error);
    }
  }

  try {
    const result = await supplierCreateOrder({
      product: order.supplierProductId,
      quantity: order.quantity,
      emails: order.activationEmails ?? undefined,
      idempotencyKey: order.supplierIdemKey,
    });
    if (result.total != null && usdToCents(result.total) > order.unitCostUsdCents * order.quantity) {
      await alertAdmins("Coût fournisseur supérieur au prévu", [`Commande ${order.reference} : facturé ${usdToCents(result.total)} cents, prévu ${order.unitCostUsdCents * order.quantity}.`], `/admin/commandes/${order.id}`);
    }
    await applySupplierOrder(order, result);
  } catch (error) {
    if (error instanceof SupplierError && error.definitive) {
      // Un essai précédent à l'issue inconnue (délai dépassé) a pu aboutir : une erreur « définitive » venue ensuite
      // ne prouve pas que rien n'a été acheté. On garde la même clé d'idempotence (code d'attente) au lieu d'en changer.
      const [earlier] = await db()
        .select({ id: schema.orderEvents.id })
        .from(schema.orderEvents)
        .where(and(eq(schema.orderEvents.orderId, order.id), eq(schema.orderEvents.type, "fulfil_retry")))
        .limit(1);
      await markFailed(order, earlier ? "uncertain_outcome" : error.code, earlier ? `Un essai précédent a une issue inconnue ; dernière réponse du fournisseur : ${error.code} — ${error.message}` : error.message);
      return;
    }
    const message = error instanceof Error ? error.message : "Erreur inconnue";
    if (order.fulfilAttempts >= MAX_FULFIL_ATTEMPTS) {
      await markFailed(order, "too_many_attempts", `Abandon après ${order.fulfilAttempts} essais. Dernière erreur : ${message}`);
      return;
    }
    // Issue incertaine : la commande reste « processing », la tâche planifiée réessaiera avec la même clé.
    await logEvent(order.id, "fulfil_retry", `Livraison à reprendre (essai ${order.fulfilAttempts}) : ${message}`);
  }
}

async function applySupplierOrder(order: Order, result: SupplierOrder): Promise<void> {
  const base = { supplierOrderId: result.id.slice(0, 64), supplierReference: result.reference ? result.reference.slice(0, 64) : null, supplierStatus: result.status.slice(0, 64), fulfilLockedAt: null };
  const who = await owner(order);

  if (result.status === "completed") {
    const items = deliveryItems(result);
    if (!items.length) {
      await markFailed(order, "empty_delivery", "Commande fournisseur terminée sans accès livré.", base);
      return;
    }
    const moved = await transition(order.id, ["processing", "activation_awaiting"], { ...base, status: "delivered", deliveryEncrypted: encrypt(JSON.stringify(items), order.id), deliveredAt: new Date(), failureCode: null, failureMessage: null });
    if (moved) {
      await logEvent(order.id, "delivered", `Accès livrés (${items.length}).`);
      if (who) await sendMail(orderDeliveredMail(who.email, order, who.lang));
    }
    return;
  }

  if (result.status === "activation_awaiting") {
    const moved = await transition(order.id, ["processing"], { ...base, status: "activation_awaiting" });
    if (moved) {
      await logEvent(order.id, "activation_awaiting", "Activation manuelle en attente chez le fournisseur.");
      if (who) await sendMail(orderActivationMail(who.email, order, who.lang));
    } else {
      await db().update(schema.orders).set({ supplierStatus: base.supplierStatus, updatedAt: new Date() }).where(eq(schema.orders.id, order.id));
    }
    return;
  }

  if (result.status === "cancelled" || result.status === "failed" || result.status === "refunded") {
    await markFailed(order, `supplier_${result.status}`, `Commande fournisseur ${result.status}.`, base);
    return;
  }

  // Statut intermédiaire inconnu : on garde la référence fournisseur et on suivra par interrogation.
  const moved = await transition(order.id, ["processing"], { ...base, status: "activation_awaiting" });
  if (moved) await logEvent(order.id, "supplier_pending", `Commande fournisseur en cours (${result.status}).`);
}

async function markFailed(order: Order, code: string, message: string, extra: Partial<Order> = {}): Promise<void> {
  const moved = await transition(order.id, ["processing", "activation_awaiting"], { ...extra, status: "failed", fulfilLockedAt: null, failureCode: code.slice(0, 64), failureMessage: message.slice(0, 500) });
  if (!moved) return;
  await logEvent(order.id, "fulfil_failed", `Livraison impossible (${code}) : ${message}`);
  const who = await owner(order);
  if (who) await sendMail(orderDelayedMail(who.email, order, who.lang));
  await alertAdmins("Commande payée non livrée", [`Commande ${order.reference} — ${order.productName} × ${order.quantity}.`, `Cause : ${code} — ${message}`, "Relancez la livraison ou remboursez le client."], `/admin/commandes/${order.id}`);
}

/** Interroge le fournisseur pour une commande en attente d'activation. */
export async function pollActivation(orderId: string): Promise<void> {
  const order = await load(orderId);
  if (!order || order.status !== "activation_awaiting" || !order.supplierOrderId) return;
  try {
    const result = await supplierGetOrder(order.supplierOrderId);
    if (result.status === "activation_awaiting") return;
    await applySupplierOrder(order, result);
  } catch (error) {
    console.error("[livraison] suivi fournisseur impossible", order.reference, error instanceof Error ? error.message : error);
  }
}

// ─── Tâche planifiée ─────────────────────────────────────────────────────────

/** Rattrape tout ce qu'un webhook manqué ou une panne aurait laissé en suspens. */
export async function runOrderMaintenance(): Promise<{ payments: number; fulfilments: number; activations: number; expired: number }> {
  const now = Date.now();
  const report = { payments: 0, fulfilments: 0, activations: 0, expired: 0 };

  const pending = await db()
    .select({ id: schema.orders.id })
    .from(schema.orders)
    .where(and(eq(schema.orders.status, "pending_payment"), lt(schema.orders.createdAt, new Date(now - 2 * 60_000))))
    .orderBy(schema.orders.updatedAt)
    .limit(15);
  for (const { id } of pending) {
    await syncPayment(id);
    // Décale la prochaine vérification de cette commande derrière les autres.
    await db().update(schema.orders).set({ updatedAt: new Date() }).where(and(eq(schema.orders.id, id), eq(schema.orders.status, "pending_payment")));
    report.payments++;
  }

  // Sans nouvelle de pawaPay après 48 h, la commande est close. Le paiement est relu juste avant : on n'expire jamais
  // une commande dont le paiement a abouti (et un paiement tardif sur une commande close est de toute façon repris).
  const stale = await db()
    .select({ id: schema.orders.id })
    .from(schema.orders)
    .where(and(eq(schema.orders.status, "pending_payment"), lt(schema.orders.createdAt, new Date(now - 48 * 3600_000))))
    .limit(20);
  for (const { id } of stale) {
    const { order: fresh, read } = await syncPaymentRead(id);
    // pawaPay injoignable : aucune preuve de non-paiement, la commande reste ouverte jusqu'à la prochaine tâche.
    if (read && fresh?.status === "pending_payment" && (await transition(id, ["pending_payment"], { status: "expired" }))) {
      await logEvent(id, "expired", "Commande close après 48 h sans confirmation de paiement.");
      report.expired++;
    }
  }

  // Paiement tardif sans webhook : les commandes closes depuis moins de 3 jours sont relues aussi (un mobile money peut aboutir après expiration).
  const closed = await db()
    .select({ id: schema.orders.id, status: schema.orders.status })
    .from(schema.orders)
    .where(and(inArray(schema.orders.status, ["expired", "payment_failed"]), gt(schema.orders.createdAt, new Date(now - 3 * 86_400_000))))
    .orderBy(schema.orders.updatedAt)
    .limit(10);
  for (const { id, status } of closed) {
    await syncPayment(id);
    await db().update(schema.orders).set({ updatedAt: new Date() }).where(and(eq(schema.orders.id, id), eq(schema.orders.status, status)));
    report.payments++;
  }

  const toFulfil = await db()
    .select({ id: schema.orders.id })
    .from(schema.orders)
    .where(or(eq(schema.orders.status, "paid"), and(eq(schema.orders.status, "processing"), or(isNull(schema.orders.fulfilLockedAt), lt(schema.orders.fulfilLockedAt, new Date(now - LOCK_MS))))))
    .limit(5);
  for (const { id } of toFulfil) {
    await fulfil(id);
    report.fulfilments++;
  }

  // Activation sans fin chez le fournisseur : au-delà de 72 h, la commande passe en échec (code d'attente) pour être traitée.
  const stuck = await db()
    .select({ id: schema.orders.id })
    .from(schema.orders)
    .where(and(eq(schema.orders.status, "activation_awaiting"), lt(schema.orders.paidAt, new Date(now - 72 * 3600_000))))
    .limit(20);
  for (const { id } of stuck) {
    const order = await load(id);
    if (order) await markFailed(order, "activation_timeout", "Activation non terminée par le fournisseur après 72 h.");
  }

  const awaiting = await db().select({ id: schema.orders.id }).from(schema.orders).where(eq(schema.orders.status, "activation_awaiting")).orderBy(schema.orders.updatedAt).limit(15);
  for (const { id } of awaiting) {
    await pollActivation(id);
    await db().update(schema.orders).set({ updatedAt: new Date() }).where(and(eq(schema.orders.id, id), eq(schema.orders.status, "activation_awaiting")));
    report.activations++;
  }
  return report;
}

// ─── Lecture ─────────────────────────────────────────────────────────────────

export async function listUserOrders(userId: string): Promise<Order[]> {
  return db().select().from(schema.orders).where(eq(schema.orders.userId, userId)).orderBy(desc(schema.orders.createdAt)).limit(200);
}

/** Commande d'un client : toujours filtrée par propriétaire. */
export async function getUserOrder(userId: string, orderId: string): Promise<Order | null> {
  if (!/^[0-9a-f-]{36}$/.test(orderId)) return null;
  const [order] = await db()
    .select()
    .from(schema.orders)
    .where(and(eq(schema.orders.id, orderId), eq(schema.orders.userId, userId)))
    .limit(1);
  return order ?? null;
}

/**
 * Renvoie une URL de paiement valide pour une commande en attente.
 * Si l'URL enregistrée est encore active, elle est réutilisée immédiatement.
 * Si elle est expirée (CloudFront pre-signed URL expirée), une nouvelle session pawaPay
 * est régénérée avec une signature neuve et mise à jour en base de données.
 */
export async function getFreshOrderPaymentUrl(orderId: string, user: User): Promise<string | null> {
  const order = await getUserOrder(user.id, orderId);
  if (!order || order.status !== "pending_payment") return null;

  if (isPaymentUrlValid(order.paymentUrl)) {
    return order.paymentUrl;
  }

  try {
    const checkout = await createPaymentPage({
      depositId: order.id,
      orderId: order.id,
      reference: order.reference,
      amountXof: order.totalXof,
      description: `${order.productName} × ${order.quantity} — commande ${order.reference}`,
      customer: {
        email: user.email,
        firstName: order.customerFirstName ?? undefined,
        lastName: order.customerLastName ?? undefined,
        country: order.customerCountry ?? undefined,
        phone: order.customerPhone ?? undefined,
      },
      returnUrl: appUrl(`/paiement/retour?commande=${order.id}`),
      lang: user.lang === "en" ? "en" : "fr",
    });

    await db()
      .update(schema.orders)
      .set({
        paymentCheckoutId: checkout.depositId,
        paymentIntentId: checkout.depositId,
        paymentUrl: checkout.redirectUrl,
        updatedAt: new Date(),
      })
      .where(eq(schema.orders.id, order.id));

    return checkout.redirectUrl;
  } catch (error) {
    console.error("[orders] rafraîchissement URL paiement impossible", error);
    return order.paymentUrl;
  }
}

/** Accès livrés, déchiffrés. À n'appeler qu'après avoir vérifié le droit d'accès à la commande. */
export function readDelivery(order: Order): string[] {
  if (order.status !== "delivered" || !order.deliveryEncrypted) return [];
  try {
    const items = JSON.parse(decrypt(order.deliveryEncrypted, order.id)) as unknown;
    return Array.isArray(items) ? items.map(String) : [];
  } catch (error) {
    console.error("[livraison] déchiffrement impossible", order.reference, error instanceof Error ? error.message : error);
    return [];
  }
}

// ─── Issue d'une commande payée non livrée ───────────────────────────────────

/**
 * Échecs dont l'issue chez le fournisseur est incertaine (l'achat a peut-être abouti) ou qui exigent un contrôle
 * humain : le client ne choisit pas seul entre avoir et remboursement, et la relance admin garde la même clé d'idempotence.
 */
const HOLD_CODES = new Set(["amount_mismatch", "too_many_attempts", "empty_delivery", "uncertain_outcome", "manual_hold", "activation_timeout"]);
/** Un identifiant d'idempotence fournisseur n'est valable que 24 h : au-delà, on ne relance plus automatiquement. */
const IDEMPOTENCY_WINDOW_MS = 23 * 3600_000;

/** Vrai si le client peut choisir lui-même entre avoir et remboursement. */
export function canCustomerResolve(order: Order): boolean {
  return order.status === "failed" && !HOLD_CODES.has(order.failureCode ?? "");
}

/**
 * Convertit une commande en échec en avoir. La transition et le crédit sont
 * dans la même transaction : une commande ne peut être créditée qu'une fois.
 */
async function creditOrder(order: Order, actor: string): Promise<boolean> {
  const done = await db().transaction(async (tx) => {
    // Ni le client ni un administrateur ne créditent un avoir une fois un remboursement chez le prestataire demandé (double dédommagement).
    const moved = await tx
      .update(schema.orders)
      .set({ status: "refunded", refundedAt: new Date(), refundMethod: "balance", updatedAt: new Date() })
      .where(and(eq(schema.orders.id, order.id), eq(schema.orders.status, "failed"), isNull(schema.orders.refundRequestedAt)));
    if (affected(moved) !== 1) return false;
    await creditWallet(tx, { userId: order.userId, amountXof: order.totalXof, kind: "order_credit", label: `Avoir — commande ${order.reference}`, orderId: order.id, actor });
    return true;
  });
  if (!done) return false;
  await logEvent(order.id, "credited", `${order.totalXof} FCFA crédités sur le solde du client.`, actor);
  const who = await owner(order);
  if (who) await sendMail(orderCreditedMail(who.email, order, who.lang));
  return true;
}

export async function customerChooseCredit(userId: string, orderId: string): Promise<boolean> {
  const order = await getUserOrder(userId, orderId);
  if (!order || !canCustomerResolve(order) || order.refundRequestedAt) return false;
  return creditOrder(order, "client");
}

/** Le client demande un remboursement sur son moyen de paiement (traité par l'équipe). */
export async function customerRequestRefund(userId: string, orderId: string): Promise<boolean> {
  const order = await getUserOrder(userId, orderId);
  // Une commande payée avec le solde se rembourse sur le solde.
  if (!order || !canCustomerResolve(order) || order.paidWith !== "gateway" || order.refundRequestedAt) return false;
  const result = await db()
    .update(schema.orders)
    .set({ refundRequestedAt: new Date(), updatedAt: new Date() })
    .where(and(eq(schema.orders.id, order.id), eq(schema.orders.status, "failed"), isNull(schema.orders.refundRequestedAt)));
  if (affected(result) !== 1) return false;
  await logEvent(order.id, "refund_requested", "Le client demande un remboursement.", "client");
  await alertAdmins("Remboursement demandé", [`Commande ${order.reference} — ${order.totalXof} FCFA.`, "Émettez le remboursement chez pawaPay, puis marquez la commande comme remboursée."], `/admin/commandes/${order.id}`);
  return true;
}

// ─── Backoffice ──────────────────────────────────────────────────────────────

export async function adminCreditOrder(orderId: string, actor: string): Promise<boolean> {
  const order = await load(orderId);
  if (!order || order.status !== "failed" || order.failureCode === "amount_mismatch") return false;
  return creditOrder(order, actor);
}

export async function adminGetOrder(orderId: string) {
  const order = await load(orderId);
  if (!order) return null;
  const [customer] = await db().select().from(schema.users).where(eq(schema.users.id, order.userId)).limit(1);
  const events = await db().select().from(schema.orderEvents).where(eq(schema.orderEvents.orderId, order.id)).orderBy(desc(schema.orderEvents.createdAt)).limit(200);
  return { order, customer: customer ?? null, events };
}

/**
 * Relance la livraison d'une commande en échec.
 * - Échec DÉFINITIF (refus net du fournisseur) : nouvelle clé d'idempotence, rien n'a été acheté.
 * - Issue INCERTAINE (timeouts répétés, livraison vide) : même clé, pour que le fournisseur renvoie l'achat
 *   d'origine s'il a abouti au lieu d'en créer un second ; refusé au-delà de 23 h (limite de la clé).
 * - Montant payé inattendu : jamais relancé.
 */
export async function adminRetryFulfilment(orderId: string, actor: string): Promise<boolean> {
  const order = await load(orderId);
  if (!order || order.status !== "failed" || order.failureCode === "amount_mismatch") return false;
  const uncertain = HOLD_CODES.has(order.failureCode ?? "");
  if (uncertain && (!order.paidAt || Date.now() - order.paidAt.getTime() > IDEMPOTENCY_WINDOW_MS)) return false;
  // Un remboursement demandé bloque la relance (sinon : livré ET remboursé). Condition posée dans l'UPDATE lui-même.
  const result = await db()
    .update(schema.orders)
    .set({ status: "paid", supplierIdemKey: uncertain ? order.supplierIdemKey : newId(), fulfilAttempts: order.failureCode === "price_changed" ? 1 : 0, fulfilLockedAt: null, failureCode: null, failureMessage: null, updatedAt: new Date() })
    .where(and(eq(schema.orders.id, orderId), eq(schema.orders.status, "failed"), isNull(schema.orders.refundRequestedAt)));
  if (affected(result) !== 1) return false;
  await logEvent(orderId, "retry", uncertain ? "Livraison relancée (même clé d'idempotence)." : "Livraison relancée depuis le backoffice.", actor);
  await fulfil(orderId);
  return true;
}

/**
 * Sort une commande bloquée (en cours ou activation sans fin) vers « échec » pour que l'équipe puisse la traiter :
 * créditer, relancer avec la même clé ou marquer remboursée. Code d'attente : le client ne choisit pas seul.
 */
export async function adminMarkFailed(orderId: string, actor: string): Promise<boolean> {
  const order = await load(orderId);
  if (!order || (order.status !== "processing" && order.status !== "activation_awaiting")) return false;
  const moved = await transition(orderId, ["processing", "activation_awaiting"], { status: "failed", fulfilLockedAt: null, failureCode: "manual_hold", failureMessage: "Commande bloquée, mise en échec depuis le backoffice." });
  if (!moved) return false;
  await logEvent(orderId, "fulfil_failed", "Commande mise en échec manuellement (bloquée).", actor);
  return true;
}

/** Enregistre un remboursement effectué auprès du prestataire de paiement. */
export async function adminMarkRefunded(orderId: string, actor: string): Promise<boolean> {
  const order = await load(orderId);
  // Une commande payée avec le solde ne se rembourse que par un avoir.
  if (!order || order.paidWith !== "gateway") return false;
  const moved = await transition(orderId, ["failed"], { status: "refunded", refundedAt: new Date(), refundMethod: "gateway" });
  if (!moved) return false;
  await logEvent(orderId, "refunded", "Commande marquée comme remboursée.", actor);
  const who = await owner(order);
  if (who) await sendMail(orderRefundedMail(who.email, order, who.lang));
  return true;
}

export async function getOrderStatus(orderId: string): Promise<OrderStatus | null> {
  return (await load(orderId))?.status ?? null;
}

export async function adminSetNote(orderId: string, note: string): Promise<void> {
  await db()
    .update(schema.orders)
    .set({ adminNote: note || null })
    .where(eq(schema.orders.id, orderId));
}
