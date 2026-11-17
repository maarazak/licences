/** Énumérations partagées serveur / client (aucun secret ici). */

export const ORDER_STATUSES = [
  "pending_payment", // commande créée, paiement non confirmé
  "paid", // paiement confirmé, livraison pas encore lancée
  "processing", // appel fournisseur en cours ou à reprendre
  "activation_awaiting", // payée chez le fournisseur, activation manuelle en attente
  "delivered", // accès disponibles
  "failed", // payée mais non livrée : à traiter par l'équipe
  "payment_failed",
  "expired",
  "cancelled",
  "refunded",
] as const;
export type OrderStatus = (typeof ORDER_STATUSES)[number];

export const ORDER_STATUS_LABELS: Record<OrderStatus, string> = {
  pending_payment: "Paiement en attente",
  paid: "Payée",
  processing: "Préparation en cours",
  activation_awaiting: "Activation en cours",
  delivered: "Livrée",
  failed: "Prise en charge par l'équipe",
  payment_failed: "Paiement échoué",
  expired: "Expirée",
  cancelled: "Annulée",
  refunded: "Remboursée",
};

/** Libellés internes (backoffice) quand ils diffèrent de ceux du client. */
export const ORDER_STATUS_ADMIN_LABELS: Record<OrderStatus, string> = {
  ...ORDER_STATUS_LABELS,
  failed: "Échec de livraison (à traiter)",
};

export type Tone = "neutral" | "info" | "success" | "warning" | "danger";

export const ORDER_STATUS_TONES: Record<OrderStatus, Tone> = {
  pending_payment: "neutral",
  paid: "info",
  processing: "info",
  activation_awaiting: "info",
  delivered: "success",
  failed: "warning",
  payment_failed: "danger",
  expired: "neutral",
  cancelled: "neutral",
  refunded: "neutral",
};

/** Statuts pour lesquels l'argent du client a été encaissé. */
export const PAID_STATUSES: OrderStatus[] = ["paid", "processing", "activation_awaiting", "delivered", "failed"];

export const TICKET_STATUSES = ["open", "answered", "closed"] as const;
export type TicketStatus = (typeof TICKET_STATUSES)[number];

export const TICKET_STATUS_LABELS: Record<TicketStatus, string> = {
  open: "En attente de réponse",
  answered: "Répondu",
  closed: "Clôturé",
};

export const TICKET_STATUS_TONES: Record<TicketStatus, Tone> = {
  open: "warning",
  answered: "success",
  closed: "neutral",
};

export const USER_ROLES = ["customer", "admin"] as const;
export type UserRole = (typeof USER_ROLES)[number];

export const MAX_QUANTITY = 50;

export { COUNTRIES, COUNTRY_CODES, type CountryCode } from "./countries";

/**
 * Libellé français du type d'accès. Chez le fournisseur, l'information se
 * trouve dans `category` (« accounts », « link », « code ») ou, à défaut, dans
 * `delivery_kind`.
 */
export function deliveryKindLabel(kind: string): string {
  const k = kind.toLowerCase();
  if (!k) return "Accès numérique";
  if (k.includes("link") || k.includes("lien")) return "Lien d'activation";
  if (k.includes("account") || k.includes("compte")) return "Compte fourni";
  if (k.includes("code") || k.includes("key") || k.includes("cle")) return "Code à copier";
  if (k.includes("email") || k.includes("invite") || k.includes("activation")) return "Activation sur votre e-mail";
  return "Accès numérique";
}
