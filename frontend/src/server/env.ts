import "server-only";
import { z } from "zod";

/**
 * Variables d'environnement serveur, validées au premier accès.
 * Ne jamais importer ce module dans un composant client.
 */
const schema = z.object({
  NODE_ENV: z.enum(["development", "test", "production"]).default("development"),
  APP_URL: z.string().url(),
  DATABASE_URL: z.string().min(1),

  AUTH_SECRET: z.string().min(32, "AUTH_SECRET doit faire au moins 32 caractères"),
  ENCRYPTION_KEY: z
    .string()
    .refine((v) => Buffer.from(v, "base64").length === 32, "ENCRYPTION_KEY doit être 32 octets en base64"),
  CRON_SECRET: z.string().min(24, "CRON_SECRET doit faire au moins 24 caractères"),

  /** En-tête portant l'IP réelle du client, posé par un reverse proxy de confiance (vide = non utilisé). */
  TRUSTED_IP_HEADER: z.string().default(""),
  /** Nombre de proxys de confiance derrière lesquels lire X-Forwarded-For (0 = dernière entrée). */
  TRUSTED_PROXY_HOPS: z.coerce.number().int().min(0).max(5).default(0),

  SUPPLIER_API_URL: z.string().url().default("https://flowseller-api.pe-supplykh.com/api/v1"),
  SUPPLIER_API_KEY: z.string().default(""),

  PAWAPAY_API_URL: z.string().url().default("https://api.sandbox.pawapay.io/v2"),
  PAWAPAY_API_TOKEN: z.string().default(""),

  /** Sans SMTP_HOST, les e-mails sont écrits dans la console (développement uniquement). */
  SMTP_HOST: z.string().default(""),
  SMTP_PORT: z.coerce.number().int().default(587),
  SMTP_USER: z.string().default(""),
  SMTP_PASSWORD: z.string().default(""),
  MAIL_FROM: z.string().default("Boutique <no-reply@localhost>"),
  SUPPORT_EMAIL: z.string().default(""),
});

export type Env = z.infer<typeof schema>;

let cached: Env | null = null;

export function env(): Env {
  if (cached) return cached;
  const parsed = schema.safeParse(process.env);
  if (!parsed.success) {
    const details = parsed.error.issues.map((i) => `- ${i.path.join(".")}: ${i.message}`).join("\n");
    throw new Error(`Configuration invalide (.env) :\n${details}`);
  }
  const e = parsed.data;
  if (e.NODE_ENV === "production" && process.env.NEXT_PHASE !== "phase-production-build") {
    const missing: string[] = [];
    if (!e.SUPPLIER_API_KEY) missing.push("SUPPLIER_API_KEY");
    if (!e.PAWAPAY_API_TOKEN) missing.push("PAWAPAY_API_TOKEN");
    if (!e.SMTP_HOST) missing.push("SMTP_HOST");
    if (!e.APP_URL.startsWith("https://")) missing.push("APP_URL (https obligatoire)");
    if (!/^https:\/\/([a-z0-9-]+\.)*pawapay\.io(\/.*)?$/.test(e.PAWAPAY_API_URL)) missing.push("PAWAPAY_API_URL (https://…pawapay.io)");
    if (!e.SUPPLIER_API_URL.startsWith("https://")) missing.push("SUPPLIER_API_URL (https obligatoire)");
    if (/@(localhost|exemple\.com|example\.com)>?$/i.test(e.MAIL_FROM)) missing.push("MAIL_FROM (adresse d'expéditeur réelle et validée chez le fournisseur SMTP)");
    if (missing.length) throw new Error(`Configuration de production incomplète : ${missing.join(", ")}`);
  }
  cached = e;
  return e;
}

export function isProd(): boolean {
  return process.env.NODE_ENV === "production";
}

export function appUrl(path = "/"): string {
  return new URL(path, env().APP_URL).toString();
}
