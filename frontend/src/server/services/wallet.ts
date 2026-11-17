import "server-only";
import { and, desc, eq, gte, or, sql } from "drizzle-orm";
import { affected, db, schema, type Tx } from "@/server/db";
import type { User, WalletDeposit, WalletEntry } from "@/server/db/schema";
import { appUrl } from "@/server/env";
import { newId, randomReference } from "@/server/security/crypto";
import { rateLimit } from "@/server/security/rate-limit";
import { createPaymentPage, getDeposit, isPaymentUrlValid, readDepositState } from "./pawapay";

/**
 * Avoir client (« Mon solde »).
 *
 * Le solde vit dans `users.balance_xof` et chaque mouvement est tracé dans
 * `wallet_entries`. Les deux écritures se font dans la même transaction, et le
 * débit est conditionnel (`balance >= montant`) : le solde ne peut ni devenir
 * négatif ni être dépensé deux fois par des requêtes simultanées.
 */

export class InsufficientBalanceError extends Error {}
export class DepositError extends Error {}

export type WalletKind = "order_credit" | "purchase" | "admin_adjustment" | "deposit";

type Movement = { userId: string; amountXof: number; kind: WalletKind; label: string; orderId?: string | null; actor?: string };

async function record(tx: Tx, m: Movement, signedAmount: number): Promise<number> {
  const [row] = await tx.select({ balance: schema.users.balanceXof }).from(schema.users).where(eq(schema.users.id, m.userId)).limit(1);
  if (!row) throw new Error("Compte introuvable");
  await tx.insert(schema.walletEntries).values({
    id: newId(),
    userId: m.userId,
    amountXof: signedAmount,
    balanceAfterXof: row.balance,
    kind: m.kind,
    orderId: m.orderId ?? null,
    label: m.label.slice(0, 255),
    actor: (m.actor ?? "system").slice(0, 191),
    createdAt: new Date(),
  });
  return row.balance;
}

/** Crédite l'avoir. À appeler dans une transaction. */
export async function creditWallet(tx: Tx, m: Movement): Promise<number> {
  if (!Number.isInteger(m.amountXof) || m.amountXof <= 0) throw new Error("Montant de crédit invalide");
  const result = await tx
    .update(schema.users)
    .set({ balanceXof: sql`${schema.users.balanceXof} + ${m.amountXof}` })
    .where(eq(schema.users.id, m.userId));
  if (affected(result) !== 1) throw new Error("Compte introuvable");
  return record(tx, m, m.amountXof);
}

/** Débite l'avoir si le solde suffit, sinon lève InsufficientBalanceError. À appeler dans une transaction. */
export async function debitWallet(tx: Tx, m: Movement): Promise<number> {
  if (!Number.isInteger(m.amountXof) || m.amountXof <= 0) throw new Error("Montant de débit invalide");
  const result = await tx
    .update(schema.users)
    .set({ balanceXof: sql`${schema.users.balanceXof} - ${m.amountXof}` })
    .where(and(eq(schema.users.id, m.userId), gte(schema.users.balanceXof, m.amountXof)));
  if (affected(result) !== 1) throw new InsufficientBalanceError("Solde insuffisant");
  return record(tx, m, -m.amountXof);
}

export async function listWalletEntries(userId: string): Promise<WalletEntry[]> {
  return db().select().from(schema.walletEntries).where(eq(schema.walletEntries.userId, userId)).orderBy(desc(schema.walletEntries.createdAt)).limit(200);
}

/** Ajustement manuel depuis le backoffice (positif ou négatif). */
export async function adminAdjustWallet(userId: string, amountXof: number, label: string, actor: string): Promise<number> {
  return db().transaction(async (tx) => {
    const movement: Movement = { userId, amountXof: Math.abs(amountXof), kind: "admin_adjustment", label, actor };
    return amountXof > 0 ? creditWallet(tx, movement) : debitWallet(tx, movement);
  });
}

export type StartDepositInput = {
  amountXof: number;
  phone: string;
  country: string;
  fullName?: string;
};

/**
 * Initialise une recharge de solde par Mobile Money via la Hosted Payment Page pawaPay.
 */
export async function startWalletDeposit(
  user: User,
  input: StartDepositInput,
): Promise<{ depositId: string; reference: string; paymentUrl: string }> {
  const depositId = newId();
  const reference = randomReference("DEP");
  const now = new Date();

  let checkout;
  try {
    checkout = await createPaymentPage({
      depositId,
      orderId: depositId,
      reference,
      amountXof: input.amountXof,
      description: `Recharge solde ${input.amountXof} F — ${reference}`,
      customer: {
        email: user.email,
        country: input.country,
        phone: input.phone,
        firstName: input.fullName?.trim() || undefined,
      },
      returnUrl: appUrl(`/paiement/retour?depot=${depositId}`),
      lang: user.lang === "en" ? "en" : "fr",
    });
  } catch (error) {
    console.error("[deposit] initialisation pawaPay impossible", error);
    throw new DepositError("Le paiement n'a pas pu être initialisé. Aucun montant n'a été débité. Réessayez dans un instant.");
  }

  await db().insert(schema.walletDeposits).values({
    id: depositId,
    reference,
    userId: user.id,
    amountXof: input.amountXof,
    customerPhone: input.phone,
    customerCountry: input.country,
    customerName: input.fullName?.trim() || null,
    status: "pending",
    paymentCheckoutId: checkout.depositId,
    paymentIntentId: checkout.depositId,
    paymentUrl: checkout.redirectUrl,
    createdAt: now,
    updatedAt: now,
  });

  return { depositId, reference, paymentUrl: checkout.redirectUrl };
}

/**
 * Relit l'état authentique du dépôt auprès de pawaPay et crédite le solde du client
 * immédiatement dès confirmation du paiement. La transaction est strictement idempotente.
 */
export async function syncWalletDeposit(depositId: string): Promise<WalletDeposit | null> {
  const [deposit] = await db()
    .select()
    .from(schema.walletDeposits)
    .where(eq(schema.walletDeposits.id, depositId))
    .limit(1);

  if (!deposit) return null;
  if (deposit.status === "completed" && deposit.creditedAt) {
    return deposit;
  }

  const pawaCheckoutId = deposit.paymentCheckoutId || deposit.id;
  let pawaPayload: unknown;
  try {
    pawaPayload = await getDeposit(pawaCheckoutId);
  } catch (error) {
    console.error("[deposit] lecture deposit pawaPay impossible", error);
    return deposit;
  }

  const state = readDepositState(pawaPayload, deposit.id);

  if (state.paymentStatus === "completed") {
    const amountOk =
      state.amount === deposit.amountXof &&
      (state.currency === "" ||
        state.currency.toUpperCase() === "XOF" ||
        state.currency.toUpperCase() === "XAF");
    if (!amountOk) {
      console.error("[deposit] montant ou devise incohérent", state, deposit);
      return deposit;
    }

    await db().transaction(async (tx) => {
      const [locked] = await tx
        .select()
        .from(schema.walletDeposits)
        .where(eq(schema.walletDeposits.id, deposit.id))
        .for("update");

      if (!locked || locked.creditedAt) return;

      await tx
        .update(schema.walletDeposits)
        .set({
          status: "completed",
          paidAt: new Date(),
          creditedAt: new Date(),
          updatedAt: new Date(),
        })
        .where(eq(schema.walletDeposits.id, deposit.id));

      await creditWallet(tx, {
        userId: deposit.userId,
        amountXof: deposit.amountXof,
        kind: "deposit",
        label: `Recharge de solde — Mobile Money (${deposit.reference})`,
        actor: "pawapay",
      });
    });

    const [updated] = await db()
      .select()
      .from(schema.walletDeposits)
      .where(eq(schema.walletDeposits.id, deposit.id))
      .limit(1);
    return updated ?? deposit;
  }

  if (state.paymentStatus === "failed") {
    await db()
      .update(schema.walletDeposits)
      .set({
        status: "failed",
        failureMessage: (state.failureMessage || "Paiement rejeté").slice(0, 255),
        updatedAt: new Date(),
      })
      .where(eq(schema.walletDeposits.id, deposit.id));

    const [updated] = await db()
      .select()
      .from(schema.walletDeposits)
      .where(eq(schema.walletDeposits.id, deposit.id))
      .limit(1);
    return updated ?? deposit;
  }

  return deposit;
}

export async function syncWalletDepositThrottled(depositId: string): Promise<WalletDeposit | null> {
  const gate = await rateLimit("sync-deposit", depositId, 6, 60);
  if (!gate.ok) {
    const [d] = await db().select().from(schema.walletDeposits).where(eq(schema.walletDeposits.id, depositId)).limit(1);
    return d ?? null;
  }
  return syncWalletDeposit(depositId);
}

export async function findWalletDepositByPayment(ids: { paymentId?: string; checkoutId?: string }): Promise<WalletDeposit | null> {
  const conditions = [];
  if (ids.paymentId) conditions.push(eq(schema.walletDeposits.paymentIntentId, ids.paymentId));
  if (ids.checkoutId) conditions.push(eq(schema.walletDeposits.paymentCheckoutId, ids.checkoutId));
  if (ids.paymentId) conditions.push(eq(schema.walletDeposits.id, ids.paymentId));
  if (!conditions.length) return null;
  const [deposit] = await db()
    .select()
    .from(schema.walletDeposits)
    .where(or(...conditions))
    .limit(1);
  return deposit ?? null;
}

export async function getUserWalletDeposit(userId: string, depositId: string): Promise<WalletDeposit | null> {
  const [deposit] = await db()
    .select()
    .from(schema.walletDeposits)
    .where(and(eq(schema.walletDeposits.id, depositId), eq(schema.walletDeposits.userId, userId)))
    .limit(1);
  return deposit ?? null;
}

/**
 * Renvoie une URL de paiement valide pour une recharge de solde en attente.
 * Si l'URL a expiré (signature CloudFront expirée), régénère une session pawaPay.
 */
export async function getFreshWalletDepositUrl(depositId: string, user: User): Promise<string | null> {
  const deposit = await getUserWalletDeposit(user.id, depositId);
  if (!deposit || deposit.status !== "pending") return null;

  if (isPaymentUrlValid(deposit.paymentUrl)) {
    return deposit.paymentUrl;
  }

  try {
    const checkout = await createPaymentPage({
      depositId: deposit.id,
      orderId: deposit.id,
      reference: deposit.reference,
      amountXof: deposit.amountXof,
      description: `Recharge solde ${deposit.amountXof} F — ${deposit.reference}`,
      customer: {
        email: user.email,
        country: deposit.customerCountry,
        phone: deposit.customerPhone,
        firstName: deposit.customerName ?? undefined,
      },
      returnUrl: appUrl(`/paiement/retour?depot=${deposit.id}`),
      lang: user.lang === "en" ? "en" : "fr",
    });

    await db()
      .update(schema.walletDeposits)
      .set({
        paymentCheckoutId: checkout.depositId,
        paymentIntentId: checkout.depositId,
        paymentUrl: checkout.redirectUrl,
        updatedAt: new Date(),
      })
      .where(eq(schema.walletDeposits.id, deposit.id));

    return checkout.redirectUrl;
  } catch (error) {
    console.error("[wallet] rafraîchissement URL dépôt impossible", error);
    return deposit.paymentUrl;
  }
}
