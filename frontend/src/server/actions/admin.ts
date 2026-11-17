"use server";

import { revalidatePath } from "next/cache";
import { eq } from "drizzle-orm";
import type { ActionState } from "@/lib/action-state";
import { actionAdmin, AuthError, destroyAllSessions } from "@/server/auth/session";
import { db, schema } from "@/server/db";
import type { User } from "@/server/db/schema";
import { audit } from "@/server/services/audit";
import { publishAllSellable, refreshSupplierBalance, setPublished, syncCatalog, updateProductSettings } from "@/server/services/catalog";
import { adminCreditOrder, adminMarkFailed, adminMarkRefunded, adminRetryFulfilment, adminSetNote, getOrderStatus, syncPayment } from "@/server/services/orders";
import { adminAdjustWallet, InsufficientBalanceError } from "@/server/services/wallet";
import { saveSettings } from "@/server/services/settings";
import { SupplierError } from "@/server/services/supplier";
import { adminReply, adminSetTicketStatus } from "@/server/services/tickets";
import { firstError, noteSchema, productEditSchema, replySchema, settingsSchema, ticketStatusSchema, uuidSchema, walletAdjustSchema } from "@/server/validators";

/**
 * Actions du backoffice. Chaque action revérifie le droit administrateur :
 * une action serveur est un point d'entrée public, la protection des pages ne suffit pas.
 */

const text = (formData: FormData, key: string): string => {
  const value = formData.get(key);
  return typeof value === "string" ? value : "";
};

async function guard(run: (admin: User) => Promise<ActionState>): Promise<ActionState> {
  let admin: User;
  try {
    admin = await actionAdmin();
  } catch (error) {
    return { error: error instanceof AuthError ? error.message : "Accès refusé." };
  }
  try {
    return await run(admin);
  } catch (error) {
    if (error instanceof SupplierError) return { error: `Fournisseur : ${error.message} (${error.code})` };
    console.error("[admin] action en échec", error);
    return { error: "L'action a échoué. Consultez les journaux du serveur." };
  }
}

export async function syncCatalogAction(): Promise<ActionState> {
  return guard(async (admin) => {
    const report = await syncCatalog();
    await refreshSupplierBalance().catch(() => undefined); // l'erreur est mémorisée pour le tableau de bord
    await audit(admin, "catalogue.synchroniser", "", `${report.total} produits, ${report.created} nouveaux, ${report.deactivated} retirés`);
    revalidatePath("/", "layout");
    return { success: `Catalogue synchronisé : ${report.total} produits lus, ${report.created} nouveaux, ${report.updated} modifiés, ${report.unchanged} inchangés, ${report.deactivated} retirés.${report.held ? ` ${report.held} produit(s) annoncés indisponibles par le fournisseur : changement en attente de confirmation (le fournisseur se corrige souvent de lui-même).` : ""}` };
  });
}

export async function publishAllAction(): Promise<ActionState> {
  return guard(async (admin) => {
    const count = await publishAllSellable();
    await audit(admin, "produits.publier-tout", "", `${count} produits`);
    revalidatePath("/", "layout");
    return { success: `${count} produit(s) publié(s).` };
  });
}

export async function togglePublishAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return guard(async (admin) => {
    const id = uuidSchema.safeParse(text(formData, "id"));
    if (!id.success) return { error: "Produit invalide." };
    const publish = text(formData, "publish") === "1";
    await setPublished([id.data], publish);
    await audit(admin, publish ? "produit.publier" : "produit.masquer", id.data);
    revalidatePath("/", "layout");
    return { success: publish ? "Produit publié." : "Produit masqué." };
  });
}

export async function updateProductAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return guard(async (admin) => {
    const parsed = productEditSchema.safeParse({
      id: text(formData, "id"),
      isPublished: formData.get("isPublished") === "on",
      isFeatured: formData.get("isFeatured") === "on",
      displayName: text(formData, "displayName").trim(),
      shopCategory: text(formData, "shopCategory").trim(),
      durationLabel: text(formData, "durationLabel").trim(),
      description: text(formData, "description").trim(),
      descriptionEn: text(formData, "descriptionEn").trim(),
      instructions: text(formData, "instructions").trim(),
      instructionsEn: text(formData, "instructionsEn").trim(),
      featuredRank: text(formData, "featuredRank").trim(),
      priceXofOverride: text(formData, "priceXofOverride").trim(),
      marginPercentOverride: text(formData, "marginPercentOverride").trim(),
    });
    if (!parsed.success) return { error: firstError(parsed.error) };
    const { id, ...edit } = parsed.data;
    await updateProductSettings(id, edit);
    await audit(admin, "produit.modifier", id, `publié=${edit.isPublished} prixFixe=${edit.priceXofOverride ?? "-"} marge=${edit.marginPercentOverride ?? "-"}`);
    revalidatePath("/", "layout");
    return { success: "Produit enregistré." };
  });
}

export async function updateSettingsAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return guard(async (admin) => {
    const parsed = settingsSchema.safeParse({
      usdRate: text(formData, "usdRate"),
      marginPercent: text(formData, "marginPercent"),
      minMarginXof: text(formData, "minMarginXof"),
      roundingStep: text(formData, "roundingStep"),
      autoPublish: formData.get("autoPublish") === "on",
      notice: text(formData, "notice"),
    });
    if (!parsed.success) return { error: firstError(parsed.error) };
    await saveSettings(parsed.data);
    await audit(admin, "parametres.modifier", "", `taux=${parsed.data.usdRate} marge=${parsed.data.marginPercent}% minimum=${parsed.data.minMarginXof} arrondi=${parsed.data.roundingStep}`);
    revalidatePath("/", "layout");
    return { success: "Paramètres enregistrés. Les prix calculés sont à jour." };
  });
}

export async function retryOrderAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return guard(async (admin) => {
    const id = uuidSchema.safeParse(text(formData, "orderId"));
    if (!id.success) return { error: "Commande invalide." };
    const ok = await adminRetryFulfilment(id.data, admin.email);
    await audit(admin, "commande.relancer", id.data, ok ? "relancée" : "refusée");
    revalidatePath(`/admin/commandes/${id.data}`);
    return ok ? { success: "Livraison relancée. Vérifiez le nouveau statut." } : { error: "Relance impossible : commande non en échec, montant à contrôler, ou délai de 23 h dépassé pour une issue incertaine (vérifiez chez le fournisseur puis créditez ou remboursez)." };
  });
}

export async function refundOrderAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return guard(async (admin) => {
    const id = uuidSchema.safeParse(text(formData, "orderId"));
    if (!id.success) return { error: "Commande invalide." };
    if (formData.get("confirm") !== "on") return { error: "Cochez la case pour confirmer que le remboursement a bien été émis." };
    const ok = await adminMarkRefunded(id.data, admin.email);
    await audit(admin, "commande.rembourser", id.data, ok ? "marquée remboursée" : "refusée");
    revalidatePath(`/admin/commandes/${id.data}`);
    return ok ? { success: "Commande marquée comme remboursée. Le client est prévenu." } : { error: "Seule une commande en échec de livraison peut être marquée remboursée." };
  });
}

export async function creditOrderAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return guard(async (admin) => {
    const id = uuidSchema.safeParse(text(formData, "orderId"));
    if (!id.success) return { error: "Commande invalide." };
    const ok = await adminCreditOrder(id.data, admin.email);
    await audit(admin, "commande.avoir", id.data, ok ? "créditée sur le solde" : "refusée");
    revalidatePath(`/admin/commandes/${id.data}`);
    return ok ? { success: "Montant crédité sur le solde du client. Il est prévenu." } : { error: "Seule une commande en échec de livraison peut être convertie en avoir." };
  });
}

export async function adjustWalletAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return guard(async (admin) => {
    const parsed = walletAdjustSchema.safeParse({ userId: text(formData, "userId"), amountXof: text(formData, "amountXof"), label: text(formData, "label") });
    if (!parsed.success) return { error: firstError(parsed.error) };
    try {
      const balance = await adminAdjustWallet(parsed.data.userId, parsed.data.amountXof, parsed.data.label, admin.email);
      await audit(admin, "solde.ajuster", parsed.data.userId, `${parsed.data.amountXof} FCFA — ${parsed.data.label}`);
      revalidatePath("/admin/clients");
      return { success: `Solde ajusté. Nouveau solde : ${balance} FCFA.` };
    } catch (error) {
      if (error instanceof InsufficientBalanceError) return { error: "Le solde du client est inférieur au montant à retirer." };
      throw error;
    }
  });
}

export async function recheckPaymentAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return guard(async (admin) => {
    const id = uuidSchema.safeParse(text(formData, "orderId"));
    if (!id.success) return { error: "Commande invalide." };
    const before = await getOrderStatus(id.data);
    const fresh = await syncPayment(id.data);
    await audit(admin, "commande.verifier-paiement", id.data, `${before ?? "?"} → ${fresh?.status ?? "?"}`);
    revalidatePath(`/admin/commandes/${id.data}`);
    if (!fresh) return { error: "Commande introuvable." };
    return { success: before === fresh.status ? "Paiement relu auprès de pawaPay : aucun changement (si pawaPay n'a pas répondu, consultez les journaux du serveur)." : "Paiement relu auprès de pawaPay : la commande a avancé." };
  });
}

export async function markFailedAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return guard(async (admin) => {
    const id = uuidSchema.safeParse(text(formData, "orderId"));
    if (!id.success) return { error: "Commande invalide." };
    const ok = await adminMarkFailed(id.data, admin.email);
    await audit(admin, "commande.mettre-en-echec", id.data, ok ? "ok" : "refusé");
    revalidatePath(`/admin/commandes/${id.data}`);
    return ok ? { success: "Commande mise en échec : vous pouvez maintenant la créditer, la relancer ou la rembourser." } : { error: "Cette commande n'est plus en cours." };
  });
}

export async function noteOrderAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return guard(async (admin) => {
    const parsed = noteSchema.safeParse({ orderId: text(formData, "orderId"), note: text(formData, "note") });
    if (!parsed.success) return { error: firstError(parsed.error) };
    await adminSetNote(parsed.data.orderId, parsed.data.note);
    await audit(admin, "commande.note", parsed.data.orderId);
    revalidatePath(`/admin/commandes/${parsed.data.orderId}`);
    return { success: "Note enregistrée." };
  });
}

export async function adminReplyTicketAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return guard(async (admin) => {
    const parsed = replySchema.safeParse({ ticketId: text(formData, "ticketId"), body: text(formData, "body") });
    if (!parsed.success) return { error: firstError(parsed.error) };
    const ok = await adminReply(admin, parsed.data.ticketId, parsed.data.body);
    if (!ok) return { error: "Demande introuvable." };
    await audit(admin, "assistance.repondre", parsed.data.ticketId);
    revalidatePath(`/admin/assistance/${parsed.data.ticketId}`);
    return { success: "Réponse envoyée. Le client est prévenu par e-mail." };
  });
}

export async function setTicketStatusAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return guard(async (admin) => {
    const parsed = ticketStatusSchema.safeParse({ ticketId: text(formData, "ticketId"), status: text(formData, "status") });
    if (!parsed.success) return { error: firstError(parsed.error) };
    await adminSetTicketStatus(parsed.data.ticketId, parsed.data.status);
    await audit(admin, "assistance.statut", parsed.data.ticketId, parsed.data.status);
    revalidatePath(`/admin/assistance/${parsed.data.ticketId}`);
    return { success: "Statut mis à jour." };
  });
}

export async function setUserBlockedAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return guard(async (admin) => {
    const id = uuidSchema.safeParse(text(formData, "userId"));
    if (!id.success) return { error: "Client invalide." };
    const blocked = text(formData, "blocked") === "1";
    const [target] = await db().select().from(schema.users).where(eq(schema.users.id, id.data)).limit(1);
    if (!target) return { error: "Client introuvable." };
    if (target.id === admin.id || target.role === "admin") return { error: "Un compte administrateur ne peut pas être suspendu ici." };
    await db().update(schema.users).set({ isBlocked: blocked }).where(eq(schema.users.id, target.id));
    if (blocked) await destroyAllSessions(target.id);
    await audit(admin, blocked ? "client.suspendre" : "client.retablir", target.id, target.email);
    revalidatePath("/admin/clients");
    return { success: blocked ? "Compte suspendu et déconnecté." : "Compte rétabli." };
  });
}
