import "server-only";
import { z } from "zod";
import { COUNTRY_CODES, MAX_QUANTITY, TICKET_STATUSES } from "@/lib/enums";
import { validatePhoneForCountry } from "@/lib/countries";

/** Schémas de validation des entrées. Toute donnée venant du navigateur passe par ici. */

const clean = (max: number) =>
  z
    .string()
    .trim()
    .max(max, `${max} caractères au maximum`)
    // Retire les caractères de contrôle (sauf saut de ligne et tabulation).
    .transform((v) => v.replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/g, ""));

export const emailSchema = z
  .string()
  .trim()
  .toLowerCase()
  .min(3, "Adresse e-mail requise")
  .max(191, "Adresse e-mail trop longue")
  .email("Adresse e-mail invalide");

export const codeSchema = z
  .string()
  .transform((v) => v.replace(/\s+/g, ""))
  .pipe(z.string().regex(/^\d{6}$/, "Le code comporte 6 chiffres"));

export const uuidSchema = z.string().regex(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/, "Identifiant invalide");

const name = (label: string) => clean(100).pipe(z.string().min(1, `${label} requis`));

const phone = clean(32)
  .transform((v) => v.replace(/[\s.-]/g, ""))
  .pipe(z.string().regex(/^\+?\d{8,15}$/, "Numéro de téléphone invalide"));

/** Champ facultatif : une chaîne vide devient null. */
const optional = <T extends z.ZodTypeAny>(schema: T) => z.preprocess((v) => (v === "" ? null : v), schema.nullable());

export const checkoutSchema = z
  .object({
    productId: uuidSchema,
    quantity: z.coerce.number().int().min(1, "Quantité invalide").max(MAX_QUANTITY, `${MAX_QUANTITY} unités au maximum`),
    fullName: z.preprocess((v) => (typeof v === "string" ? v.trim() : ""), z.string().max(200).optional()),
    firstName: z.preprocess((v) => (typeof v === "string" ? v.trim() : ""), z.string().max(100).optional()),
    lastName: z.preprocess((v) => (typeof v === "string" ? v.trim() : ""), z.string().max(100).optional()),
    phone: z.preprocess((v) => (typeof v === "string" ? v.trim() : ""), z.string().min(1, "Veuillez renseigner votre numéro de téléphone").max(32)),
    country: z.preprocess(
      (v) => (typeof v === "string" && COUNTRY_CODES.includes(v as (typeof COUNTRY_CODES)[number]) ? v : "BF"),
      z.enum(COUNTRY_CODES, { message: "Veuillez sélectionner un pays valide" }),
    ),
    expectedTotalXof: z.coerce.number({ message: "Total invalide" }).int().min(1, "Total invalide"),
    clientToken: z.preprocess((v) => (v === "" ? undefined : v), z.string().regex(/^[0-9a-f-]{36}$/).optional()),
    activationEmails: z
      .array(emailSchema)
      .max(MAX_QUANTITY)
      .refine((list) => new Set(list).size === list.length, "Chaque adresse d'activation doit être différente"),
    acceptTerms: z.string().optional(),
  })
  .superRefine((data, ctx) => {
    const hasName =
      (data.fullName && data.fullName.trim().length >= 2) ||
      (data.firstName && data.firstName.trim().length >= 2);
    if (!hasName) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: "Veuillez renseigner votre nom complet",
        path: ["fullName"],
      });
    }
    const check = validatePhoneForCountry(data.phone, data.country);
    if (!check.valid && check.message) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: check.message,
        path: ["phone"],
      });
    }
  });

export const profileSchema = z.object({ firstName: optional(name("Prénom")), lastName: optional(name("Nom")), phone: optional(phone), country: optional(z.enum(COUNTRY_CODES, { message: "Pays invalide" })) });

export const ticketSchema = z.object({
  subject: clean(200).pipe(z.string().min(3, "Objet trop court")),
  body: clean(4000).pipe(z.string().min(10, "Décrivez votre demande (10 caractères au minimum)")),
  orderId: optional(uuidSchema),
});

export const replySchema = z.object({ ticketId: uuidSchema, body: clean(4000).pipe(z.string().min(2, "Message trop court")) });

// ─── Backoffice ──────────────────────────────────────────────────────────────

const int = (label: string, min: number, max: number) =>
  z.coerce.number({ message: `${label} : nombre attendu` }).int(`${label} : nombre entier attendu`).min(min, `${label} : minimum ${min}`).max(max, `${label} : maximum ${max}`);

export const productEditSchema = z.object({
  id: uuidSchema,
  isPublished: z.boolean(),
  isFeatured: z.boolean(),
  displayName: optional(clean(255).pipe(z.string().min(1))),
  shopCategory: optional(clean(100).pipe(z.string().min(1))),
  durationLabel: optional(clean(60).pipe(z.string().min(1))),
  description: optional(clean(5000).pipe(z.string().min(1))),
  descriptionEn: optional(clean(5000).pipe(z.string().min(1))),
  instructions: optional(clean(5000).pipe(z.string().min(1))),
  instructionsEn: optional(clean(5000).pipe(z.string().min(1))),
  featuredRank: optional(int("Rang", 1, 999)),
  priceXofOverride: optional(int("Prix fixe", 1, 100_000_000)),
  marginPercentOverride: optional(int("Marge", 0, 1000)),
});

export const settingsSchema = z.object({
  usdRate: int("Taux", 100, 100_000),
  marginPercent: int("Marge", 0, 1000),
  minMarginXof: int("Marge minimale", 0, 1_000_000),
  roundingStep: int("Arrondi", 1, 10_000),
  autoPublish: z.boolean(),
  notice: clean(300),
});

export const walletDepositSchema = z
  .object({
    amountXof: z.coerce
      .number({ message: "Montant invalide" })
      .int("Le montant doit être un entier")
      .min(500, "Le montant minimum de dépôt est de 500 FCFA")
      .max(1_000_000, "Le montant maximum par dépôt est de 1 000 000 FCFA"),
    fullName: z.preprocess((v) => (typeof v === "string" ? v.trim() : ""), z.string().max(200).optional()),
    phone: z.preprocess((v) => (typeof v === "string" ? v.trim() : ""), z.string().min(1, "Veuillez renseigner votre numéro de téléphone").max(32)),
    country: z.preprocess(
      (v) => (typeof v === "string" && COUNTRY_CODES.includes(v as (typeof COUNTRY_CODES)[number]) ? v : "BF"),
      z.enum(COUNTRY_CODES, { message: "Veuillez sélectionner un pays valide" }),
    ),
  })
  .superRefine((data, ctx) => {
    const check = validatePhoneForCountry(data.phone, data.country);
    if (!check.valid && check.message) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: check.message,
        path: ["phone"],
      });
    }
  });

export const walletAdjustSchema = z.object({
  userId: uuidSchema,
  amountXof: int("Montant", -10_000_000, 10_000_000).refine((v) => v !== 0, "Montant : différent de zéro"),
  label: clean(200).pipe(z.string().min(3, "Motif requis")),
});

export const noteSchema = z.object({ orderId: uuidSchema, note: clean(2000) });
export const ticketStatusSchema = z.object({ ticketId: uuidSchema, status: z.enum(TICKET_STATUSES) });

/** Premier message d'erreur lisible d'une validation échouée. */
export function firstError(error: z.ZodError): string {
  return error.issues[0]?.message ?? "Données invalides";
}
