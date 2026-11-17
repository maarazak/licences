"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { eq } from "drizzle-orm";
import type { ActionState } from "@/lib/action-state";
import { isLang } from "@/lib/i18n";
import { isCurrency } from "@/lib/money";
import { actionUser, AuthError, currentUser, destroyAllSessions } from "@/server/auth/session";
import { db, schema } from "@/server/db";
import { isProd } from "@/server/env";
import { rateLimit, waitMessage } from "@/server/security/rate-limit";
import { addAlert, removeAlert } from "@/server/services/alerts";
import { CheckoutError, customerChooseCredit, customerRequestRefund, startCheckout } from "@/server/services/orders";
import { createTicket, customerReply } from "@/server/services/tickets";
import { DepositError, startWalletDeposit } from "@/server/services/wallet";
import { checkoutSchema, firstError, profileSchema, replySchema, ticketSchema, uuidSchema, walletDepositSchema } from "@/server/validators";

const text = (formData: FormData, key: string): string => {
  const value = formData.get(key);
  return typeof value === "string" ? value : "";
};

/** Valide la commande puis envoie le client sur la page de paiement. */
export async function checkoutAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  let user;
  try {
    user = await actionUser();
  } catch (error) {
    return { error: error instanceof AuthError ? error.message : "Connexion requise." };
  }

  const parsed = checkoutSchema.safeParse({
    productId: text(formData, "productId"),
    quantity: text(formData, "quantity") || "1",
    fullName: text(formData, "fullName"),
    firstName: text(formData, "firstName"),
    lastName: text(formData, "lastName"),
    phone: text(formData, "phone"),
    country: text(formData, "country"),
    expectedTotalXof: text(formData, "expectedTotalXof"),
    clientToken: text(formData, "clientToken"),
    activationEmails: formData
      .getAll("activationEmail")
      .filter((v): v is string => typeof v === "string" && v.trim() !== ""),
    acceptTerms: text(formData, "acceptTerms"),
  });
  if (!parsed.success) return { error: firstError(parsed.error) };

  const limit = await rateLimit("checkout", user.id, 12, 3600);
  if (!limit.ok) return { error: waitMessage(limit.retryAfterSeconds) };

  let destination: string;
  try {
    const input = { ...parsed.data, payWithBalance: text(formData, "payWith") === "balance" };
    const result = await startCheckout(user, input);
    // Paiement par solde : la commande est déjà payée, on va directement à son suivi.
    destination = result.paymentUrl ?? `/compte/achats/${result.orderId}`;
  } catch (error) {
    if (error instanceof CheckoutError) {
      // Prix ou stock modifiés : on rafraîchit la page pour que le nouveau total soit celui envoyé au prochain essai.
      revalidatePath("/commande/validation");
      return { error: error.message };
    }
    console.error("[checkout] erreur inattendue", error);
    return { error: "Une erreur est survenue. Aucun montant n'a été débité. Réessayez dans un instant." };
  }
  // Les coordonnées sont mémorisées pour préremplir la prochaine commande. Un échec ici ne doit jamais
  // faire croire au client que la commande (peut-être déjà payée avec son solde) n'a pas été créée.
  try {
    const userUpdates: Record<string, unknown> = {};
    if (parsed.data.phone !== undefined) userUpdates.phone = parsed.data.phone;
    if (parsed.data.country !== undefined) userUpdates.country = parsed.data.country;
    if (parsed.data.fullName) {
      const parts = parsed.data.fullName.trim().split(/\s+/);
      userUpdates.firstName = parts[0] || null;
      userUpdates.lastName = parts.slice(1).join(" ") || null;
    } else {
      if (parsed.data.firstName !== undefined) userUpdates.firstName = parsed.data.firstName || null;
      if (parsed.data.lastName !== undefined) userUpdates.lastName = parsed.data.lastName || null;
    }
    if (Object.keys(userUpdates).length > 0) {
      await db().update(schema.users).set(userUpdates).where(eq(schema.users.id, user.id));
    }
  } catch (error) {
    console.error("[checkout] coordonnées non enregistrées", error);
  }
  redirect(destination);
}

/** Commande payée non livrée : le client choisit un avoir sur son solde. */
export async function chooseCreditAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  let user;
  try {
    user = await actionUser();
  } catch (error) {
    return { error: error instanceof AuthError ? error.message : "Connexion requise." };
  }
  const id = uuidSchema.safeParse(text(formData, "orderId"));
  if (!id.success) return { error: "Commande invalide." };
  const ok = await customerChooseCredit(user.id, id.data);
  revalidatePath("/compte", "layout");
  return ok ? { success: "Le montant est crédité sur votre solde." } : { error: "Cette commande ne peut pas être convertie en avoir." };
}

/** Commande payée non livrée : le client demande un remboursement. */
export async function requestRefundAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  let user;
  try {
    user = await actionUser();
  } catch (error) {
    return { error: error instanceof AuthError ? error.message : "Connexion requise." };
  }
  const id = uuidSchema.safeParse(text(formData, "orderId"));
  if (!id.success) return { error: "Commande invalide." };
  const ok = await customerRequestRefund(user.id, id.data);
  revalidatePath("/compte", "layout");
  return ok ? { success: "Votre demande de remboursement est enregistrée. Notre équipe la traite." } : { error: "Le remboursement ne peut pas être demandé pour cette commande." };
}

export async function addAlertAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  let user;
  try {
    user = await actionUser();
  } catch (error) {
    return { error: error instanceof AuthError ? error.message : "Connexion requise." };
  }
  const id = uuidSchema.safeParse(text(formData, "productId"));
  if (!id.success) return { error: "Offre invalide." };
  const limit = await rateLimit("alert-add", user.id, 40, 3600);
  if (!limit.ok) return { error: waitMessage(limit.retryAfterSeconds) };
  const result = await addAlert(user.id, id.data);
  if (result === "unknown") return { error: "Offre introuvable." };
  if (result === "limit") return { error: "Vous avez atteint le nombre maximal d'alertes actives." };
  revalidatePath("/compte", "layout");
  return { success: "C'est noté : vous recevrez un e-mail au retour en stock." };
}

export async function removeAlertAction(formData: FormData): Promise<void> {
  const user = await actionUser();
  const id = uuidSchema.safeParse(text(formData, "alertId"));
  if (!id.success) return;
  await removeAlert(user.id, id.data);
  revalidatePath("/compte", "layout");
}

export async function updateProfileAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  let user;
  try {
    user = await actionUser();
  } catch (error) {
    return { error: error instanceof AuthError ? error.message : "Connexion requise." };
  }
  const parsed = profileSchema.safeParse({ firstName: text(formData, "firstName"), lastName: text(formData, "lastName"), phone: text(formData, "phone"), country: text(formData, "country") });
  if (!parsed.success) return { error: firstError(parsed.error) };
  await db().update(schema.users).set(parsed.data).where(eq(schema.users.id, user.id));
  revalidatePath("/compte");
  return { success: "Vos informations sont enregistrées." };
}

export async function setRemindersAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  let user;
  try {
    user = await actionUser();
  } catch (error) {
    return { error: error instanceof AuthError ? error.message : "Connexion requise." };
  }
  const enabled = formData.get("mailReminders") === "on";
  await db().update(schema.users).set({ mailReminders: enabled }).where(eq(schema.users.id, user.id));
  revalidatePath("/compte/profil");
  return { success: enabled ? "Les rappels de paiement sont activés." : "Les rappels de paiement sont désactivés." };
}

/** Ferme toutes les sessions du compte, sur tous les appareils. */
export async function logoutEverywhereAction(): Promise<void> {
  const user = await actionUser();
  await destroyAllSessions(user.id);
  redirect("/connexion");
}

export async function createTicketAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  let user;
  try {
    user = await actionUser();
  } catch (error) {
    return { error: error instanceof AuthError ? error.message : "Connexion requise." };
  }
  const parsed = ticketSchema.safeParse({ subject: text(formData, "subject"), body: text(formData, "body"), orderId: text(formData, "orderId") });
  if (!parsed.success) return { error: firstError(parsed.error) };
  const limit = await rateLimit("ticket-create", user.id, 8, 86_400);
  if (!limit.ok) return { error: waitMessage(limit.retryAfterSeconds) };
  const ticket = await createTicket(user, parsed.data);
  redirect(`/compte/assistance/${ticket.id}`);
}

export async function replyTicketAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  let user;
  try {
    user = await actionUser();
  } catch (error) {
    return { error: error instanceof AuthError ? error.message : "Connexion requise." };
  }
  const parsed = replySchema.safeParse({ ticketId: text(formData, "ticketId"), body: text(formData, "body") });
  if (!parsed.success) return { error: firstError(parsed.error) };
  const limit = await rateLimit("ticket-reply", user.id, 30, 3600);
  if (!limit.ok) return { error: waitMessage(limit.retryAfterSeconds) };
  const ok = await customerReply(user, parsed.data.ticketId, parsed.data.body);
  if (!ok) return { error: "Cette demande est clôturée ou introuvable." };
  revalidatePath(`/compte/assistance/${parsed.data.ticketId}`);
  return { success: "Message envoyé." };
}

/** Devise d'affichage des prix (FCFA ou dollar). Le paiement reste toujours en FCFA. */
export async function setCurrencyAction(formData: FormData): Promise<void> {
  const value = text(formData, "currency");
  if (!isCurrency(value)) return;
  (await cookies()).set("devise", value, { httpOnly: true, secure: isProd(), sameSite: "lax", path: "/", maxAge: 365 * 86_400 });
  revalidatePath("/", "layout");
}

/** Change la langue de l'interface (et des e-mails du compte connecté). */
export async function setLangAction(formData: FormData): Promise<void> {
  const value = text(formData, "lang");
  if (!isLang(value)) return;
  (await cookies()).set("lang", value, { httpOnly: true, secure: isProd(), sameSite: "lax", path: "/", maxAge: 365 * 86_400 });
  const user = await currentUser();
  if (user && user.lang !== value) await db().update(schema.users).set({ lang: value }).where(eq(schema.users.id, user.id));
  revalidatePath("/", "layout");
}

/** Initialise une recharge de solde par Mobile Money. */
export async function startWalletDepositAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  let user;
  try {
    user = await actionUser();
  } catch {
    return { error: "Veuillez vous connecter pour recharger votre solde." };
  }

  const parsed = walletDepositSchema.safeParse({
    amountXof: formData.get("amountXof"),
    phone: formData.get("phone"),
    country: formData.get("country"),
    fullName: formData.get("fullName"),
  });

  if (!parsed.success) return { error: firstError(parsed.error) };

  const limit = await rateLimit("wallet-deposit", user.id, 10, 3600);
  if (!limit.ok) return { error: waitMessage(limit.retryAfterSeconds) };

  let destination: string;
  try {
    const result = await startWalletDeposit(user, parsed.data);
    destination = result.paymentUrl;
  } catch (error) {
    if (error instanceof DepositError) {
      return { error: error.message };
    }
    console.error("[deposit] erreur inattendue", error);
    return { error: "Le paiement n'a pas pu être initialisé. Aucun montant n'a été débité. Réessayez dans un instant." };
  }

  // Mémorise les coordonnées pour les prochains achats
  try {
    const userUpdates: Record<string, unknown> = {};
    if (parsed.data.phone) userUpdates.phone = parsed.data.phone;
    if (parsed.data.country) userUpdates.country = parsed.data.country;
    if (parsed.data.fullName) {
      const parts = parsed.data.fullName.trim().split(/\s+/);
      if (parts[0]) userUpdates.firstName = parts[0];
      if (parts.length > 1) userUpdates.lastName = parts.slice(1).join(" ");
    }
    if (Object.keys(userUpdates).length > 0) {
      await db().update(schema.users).set(userUpdates).where(eq(schema.users.id, user.id));
    }
  } catch (err) {
    console.error("[deposit] coordonnées non enregistrées", err);
  }

  redirect(destination);
}
