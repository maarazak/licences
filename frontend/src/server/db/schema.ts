import {
  boolean,
  datetime,
  index,
  int,
  json,
  mysqlEnum,
  mysqlTable,
  text,
  uniqueIndex,
  varchar,
} from "drizzle-orm/mysql-core";
import { ORDER_STATUSES, TICKET_STATUSES, USER_ROLES } from "../../lib/enums";

/**
 * Conventions :
 * - identifiants : UUID générés côté application (varchar 36) ;
 * - montants FCFA : entiers (le franc CFA n'a pas de décimales) ;
 * - montants USD : entiers en cents ;
 * - dates : UTC.
 */
const id = (name = "id") => varchar(name, { length: 36 });
const dt = (name: string) => datetime(name, { mode: "date" });
/** Journaux : précision à la milliseconde pour ordonner les événements d'une même seconde. */
const dtMs = (name: string) => datetime(name, { mode: "date", fsp: 3 });

export const users = mysqlTable(
  "users",
  {
    id: id().primaryKey(),
    email: varchar("email", { length: 191 }).notNull(),
    firstName: varchar("first_name", { length: 100 }),
    lastName: varchar("last_name", { length: 100 }),
    phone: varchar("phone", { length: 32 }),
    country: varchar("country", { length: 2 }),
    /** Avoir du client en FCFA. Ne se modifie que par les fonctions de `services/wallet.ts`. */
    balanceXof: int("balance_xof").notNull().default(0),
    role: mysqlEnum("role", USER_ROLES).notNull().default("customer"),
    /** Mot de passe haché (scrypt) : réservé aux administrateurs, qui n'utilisent pas la connexion par code. */
    passwordHash: varchar("password_hash", { length: 255 }),
    isBlocked: boolean("is_blocked").notNull().default(false),
    /** Langue de l'interface et des e-mails (fr / en). */
    lang: varchar("lang", { length: 2 }).notNull().default("fr"),
    /** Autorise les rappels par e-mail pour un paiement non terminé. */
    mailReminders: boolean("mail_reminders").notNull().default(true),
    lastLoginAt: dt("last_login_at"),
    createdAt: dt("created_at").notNull(),
  },
  (t) => [uniqueIndex("users_email_uq").on(t.email)],
);

export const loginCodes = mysqlTable(
  "login_codes",
  {
    id: id().primaryKey(),
    email: varchar("email", { length: 191 }).notNull(),
    codeHash: varchar("code_hash", { length: 64 }).notNull(),
    attempts: int("attempts").notNull().default(0),
    expiresAt: dt("expires_at").notNull(),
    consumedAt: dt("consumed_at"),
    ip: varchar("ip", { length: 64 }),
    createdAt: dt("created_at").notNull(),
  },
  (t) => [index("login_codes_email_idx").on(t.email, t.createdAt)],
);

export const sessions = mysqlTable(
  "sessions",
  {
    id: id().primaryKey(),
    tokenHash: varchar("token_hash", { length: 64 }).notNull(),
    userId: id("user_id").notNull(),
    expiresAt: dt("expires_at").notNull(),
    ip: varchar("ip", { length: 64 }),
    userAgent: varchar("user_agent", { length: 255 }),
    createdAt: dt("created_at").notNull(),
  },
  (t) => [uniqueIndex("sessions_token_uq").on(t.tokenHash), index("sessions_user_idx").on(t.userId)],
);

export const rateLimits = mysqlTable("rate_limits", {
  key: varchar("key", { length: 191 }).primaryKey(),
  count: int("count").notNull().default(0),
  resetAt: dt("reset_at").notNull(),
});

export const products = mysqlTable(
  "products",
  {
    id: id().primaryKey(),
    supplierId: varchar("supplier_id", { length: 64 }).notNull(),
    slug: varchar("slug", { length: 191 }).notNull(),
    name: varchar("name", { length: 255 }).notNull(),
    category: varchar("category", { length: 100 }).notNull().default(""),
    imageUrl: varchar("image_url", { length: 1024 }),
    deliveryKind: varchar("delivery_kind", { length: 64 }).notNull().default(""),
    /** Prix d'achat revendeur, en cents USD. */
    costUsdCents: int("cost_usd_cents").notNull(),
    listUsdCents: int("list_usd_cents").notNull().default(0),
    /** null = quantité non dénombrable chez le fournisseur. */
    stock: int("stock"),
    inStock: boolean("in_stock").notNull().default(false),
    preorder: boolean("preorder").notNull().default(false),
    expireDays: int("expire_days").notNull().default(0),
    warranty: varchar("warranty", { length: 100 }).notNull().default(""),
    requiresEmail: boolean("requires_email").notNull().default(false),
    supplierPurchasable: boolean("supplier_purchasable").notNull().default(false),
    /** false si le produit a disparu du catalogue fournisseur. */
    supplierActive: boolean("supplier_active").notNull().default(true),
    /**
     * Lectures consécutives où le fournisseur a annoncé le produit indisponible alors qu'il était vendable. Le fournisseur
     * annonce parfois, pendant quelques minutes, « stock 0 / non vendable » à tort : la dégradation n'est enregistrée
     * qu'après plusieurs lectures concordantes (voir `applyReading`).
     */
    unavailableReadings: int("unavailable_readings").notNull().default(0),

    // Réglages boutique (jamais écrasés par la synchronisation)
    isPublished: boolean("is_published").notNull().default(false),
    isFeatured: boolean("is_featured").notNull().default(false),
    /** Ordre d'affichage des produits phares (1 = en premier). */
    featuredRank: int("featured_rank"),
    displayName: varchar("display_name", { length: 255 }),
    /** Rayon de la boutique (le champ `category` du fournisseur décrit en réalité le type d'accès). */
    shopCategory: varchar("shop_category", { length: 100 }),
    /** Durée affichée quand le fournisseur ne la renseigne pas (« 1 mois », « 2 ans »…). */
    durationLabel: varchar("duration_label", { length: 60 }),
    /** Texte brut du fournisseur (souvent en anglais) : aide à la rédaction et mode d'emploi par défaut. */
    supplierDescription: text("supplier_description"),
    description: text("description"),
    /** Mode d'emploi affiché sur la fiche et avec les accès livrés. */
    instructions: text("instructions"),
    /** Versions anglaises (repli sur le français si vides). */
    descriptionEn: text("description_en"),
    instructionsEn: text("instructions_en"),
    priceXofOverride: int("price_xof_override"),
    marginPercentOverride: int("margin_percent_override"),

    syncedAt: dt("synced_at").notNull(),
    createdAt: dt("created_at").notNull(),
    updatedAt: dt("updated_at").notNull(),
  },
  (t) => [
    uniqueIndex("products_supplier_uq").on(t.supplierId),
    uniqueIndex("products_slug_uq").on(t.slug),
    index("products_listing_idx").on(t.isPublished, t.supplierActive, t.shopCategory),
  ],
);

export const orders = mysqlTable(
  "orders",
  {
    id: id().primaryKey(),
    reference: varchar("reference", { length: 24 }).notNull(),
    /** Jeton unique par validation du formulaire : un double clic ne crée qu'une commande. */
    clientToken: varchar("client_token", { length: 36 }),
    userId: id("user_id").notNull(),
    productId: id("product_id").notNull(),
    productName: varchar("product_name", { length: 255 }).notNull(),
    supplierProductId: varchar("supplier_product_id", { length: 64 }).notNull(),
    quantity: int("quantity").notNull(),
    unitPriceXof: int("unit_price_xof").notNull(),
    totalXof: int("total_xof").notNull(),
    /** Instantanés au moment de l'achat, pour le calcul de marge. */
    unitCostUsdCents: int("unit_cost_usd_cents").notNull(),
    usdRate: int("usd_rate").notNull(),
    warranty: varchar("warranty", { length: 100 }).notNull().default(""),
    expireDays: int("expire_days").notNull().default(0),

    customerFirstName: varchar("customer_first_name", { length: 100 }).notNull(),
    customerLastName: varchar("customer_last_name", { length: 100 }).notNull(),
    customerPhone: varchar("customer_phone", { length: 32 }),
    customerCountry: varchar("customer_country", { length: 2 }),
    activationEmails: json("activation_emails").$type<string[]>(),

    status: mysqlEnum("status", ORDER_STATUSES).notNull().default("pending_payment"),

    paymentCheckoutId: varchar("payment_checkout_id", { length: 191 }),
    paymentIntentId: varchar("payment_intent_id", { length: 191 }),
    paymentUrl: text("payment_url"),
    paidAt: dt("paid_at"),
    /** « gateway » (Orqex) ou « balance » (avoir du client). */
    paidWith: varchar("paid_with", { length: 16 }).notNull().default("gateway"),

    /** Relances e-mail d'un paiement non terminé (2 au maximum). */
    reminderCount: int("reminder_count").notNull().default(0),
    lastReminderAt: dt("last_reminder_at"),

    supplierIdemKey: varchar("supplier_idem_key", { length: 64 }).notNull(),
    supplierOrderId: varchar("supplier_order_id", { length: 64 }),
    supplierReference: varchar("supplier_reference", { length: 64 }),
    supplierStatus: varchar("supplier_status", { length: 64 }),
    fulfilAttempts: int("fulfil_attempts").notNull().default(0),
    fulfilLockedAt: dt("fulfil_locked_at"),
    /** Accès livrés, chiffrés (AES-256-GCM). */
    deliveryEncrypted: text("delivery_encrypted"),
    deliveredAt: dt("delivered_at"),

    failureCode: varchar("failure_code", { length: 64 }),
    failureMessage: varchar("failure_message", { length: 500 }),
    /** Le client a demandé un remboursement sur son moyen de paiement. */
    refundRequestedAt: dt("refund_requested_at"),
    refundedAt: dt("refunded_at"),
    /** « balance » (avoir crédité) ou « gateway » (remboursé chez le prestataire). */
    refundMethod: varchar("refund_method", { length: 16 }),
    adminNote: text("admin_note"),

    createdAt: dt("created_at").notNull(),
    updatedAt: dt("updated_at").notNull(),
  },
  (t) => [
    uniqueIndex("orders_reference_uq").on(t.reference),
    uniqueIndex("orders_client_token_uq").on(t.userId, t.clientToken),
    uniqueIndex("orders_intent_uq").on(t.paymentIntentId),
    uniqueIndex("orders_checkout_uq").on(t.paymentCheckoutId),
    index("orders_user_idx").on(t.userId, t.createdAt),
    index("orders_status_idx").on(t.status, t.updatedAt),
  ],
);

export const orderEvents = mysqlTable(
  "order_events",
  {
    id: id().primaryKey(),
    orderId: id("order_id").notNull(),
    type: varchar("type", { length: 64 }).notNull(),
    message: varchar("message", { length: 500 }).notNull(),
    actor: varchar("actor", { length: 191 }).notNull().default("system"),
    createdAt: dtMs("created_at").notNull(),
  },
  (t) => [index("order_events_order_idx").on(t.orderId, t.createdAt)],
);

export const tickets = mysqlTable(
  "tickets",
  {
    id: id().primaryKey(),
    reference: varchar("reference", { length: 24 }).notNull(),
    userId: id("user_id").notNull(),
    orderId: id("order_id"),
    subject: varchar("subject", { length: 200 }).notNull(),
    status: mysqlEnum("status", TICKET_STATUSES).notNull().default("open"),
    createdAt: dt("created_at").notNull(),
    updatedAt: dt("updated_at").notNull(),
  },
  (t) => [
    uniqueIndex("tickets_reference_uq").on(t.reference),
    index("tickets_user_idx").on(t.userId, t.updatedAt),
    index("tickets_status_idx").on(t.status, t.updatedAt),
  ],
);

export const ticketMessages = mysqlTable(
  "ticket_messages",
  {
    id: id().primaryKey(),
    ticketId: id("ticket_id").notNull(),
    authorId: id("author_id").notNull(),
    isStaff: boolean("is_staff").notNull().default(false),
    body: text("body").notNull(),
    createdAt: dt("created_at").notNull(),
  },
  (t) => [index("ticket_messages_ticket_idx").on(t.ticketId, t.createdAt)],
);

/** Grand livre de l'avoir client : chaque mouvement est une ligne, jamais modifiée. */
export const walletEntries = mysqlTable(
  "wallet_entries",
  {
    id: id().primaryKey(),
    userId: id("user_id").notNull(),
    /** Positif = crédit, négatif = débit. */
    amountXof: int("amount_xof").notNull(),
    balanceAfterXof: int("balance_after_xof").notNull(),
    kind: varchar("kind", { length: 32 }).notNull(),
    orderId: id("order_id"),
    label: varchar("label", { length: 255 }).notNull(),
    actor: varchar("actor", { length: 191 }).notNull().default("system"),
    createdAt: dtMs("created_at").notNull(),
  },
  (t) => [index("wallet_entries_user_idx").on(t.userId, t.createdAt), uniqueIndex("wallet_entries_order_kind_uq").on(t.orderId, t.kind)],
);

/** Recharge / Dépôt sur le solde via Mobile Money (pawaPay). */
export const walletDeposits = mysqlTable(
  "wallet_deposits",
  {
    id: id().primaryKey(),
    reference: varchar("reference", { length: 32 }).notNull().unique(),
    userId: id("user_id").notNull(),
    amountXof: int("amount_xof").notNull(),
    customerPhone: varchar("customer_phone", { length: 32 }).notNull(),
    customerCountry: varchar("customer_country", { length: 8 }).notNull().default("BF"),
    customerName: varchar("customer_name", { length: 150 }),
    status: varchar("status", { length: 24 }).notNull().default("pending"),
    paymentCheckoutId: varchar("payment_checkout_id", { length: 191 }),
    paymentIntentId: varchar("payment_intent_id", { length: 191 }),
    paymentUrl: text("payment_url"),
    paidAt: dt("paid_at"),
    creditedAt: dt("credited_at"),
    failureMessage: varchar("failure_message", { length: 255 }),
    createdAt: dtMs("created_at").notNull(),
    updatedAt: dtMs("updated_at").notNull(),
  },
  (t) => [
    index("wallet_deposits_user_idx").on(t.userId, t.createdAt),
    index("wallet_deposits_status_idx").on(t.status),
    index("wallet_deposits_checkout_idx").on(t.paymentCheckoutId),
    index("wallet_deposits_intent_idx").on(t.paymentIntentId),
  ],
);

/** Alerte de retour en stock : un seul e-mail, puis l'alerte s'arrête. */
export const stockAlerts = mysqlTable(
  "stock_alerts",
  {
    id: id().primaryKey(),
    userId: id("user_id").notNull(),
    productId: id("product_id").notNull(),
    notifiedAt: dt("notified_at"),
    createdAt: dt("created_at").notNull(),
  },
  (t) => [uniqueIndex("stock_alerts_user_product_uq").on(t.userId, t.productId), index("stock_alerts_pending_idx").on(t.notifiedAt, t.productId)],
);

export const settings = mysqlTable("settings", {
  key: varchar("key", { length: 64 }).primaryKey(),
  value: text("value").notNull(),
  updatedAt: dt("updated_at").notNull(),
});

export const auditLogs = mysqlTable(
  "audit_logs",
  {
    id: id().primaryKey(),
    adminId: id("admin_id").notNull(),
    adminEmail: varchar("admin_email", { length: 191 }).notNull(),
    action: varchar("action", { length: 100 }).notNull(),
    target: varchar("target", { length: 191 }).notNull().default(""),
    details: varchar("details", { length: 1000 }).notNull().default(""),
    ip: varchar("ip", { length: 64 }),
    createdAt: dtMs("created_at").notNull(),
  },
  (t) => [index("audit_logs_created_idx").on(t.createdAt)],
);

export type User = typeof users.$inferSelect;
export type Product = typeof products.$inferSelect;
export type Order = typeof orders.$inferSelect;
export type OrderEvent = typeof orderEvents.$inferSelect;
export type Ticket = typeof tickets.$inferSelect;
export type TicketMessage = typeof ticketMessages.$inferSelect;
export type WalletEntry = typeof walletEntries.$inferSelect;
export type WalletDeposit = typeof walletDeposits.$inferSelect;
export type StockAlert = typeof stockAlerts.$inferSelect;
export type AuditLog = typeof auditLogs.$inferSelect;
