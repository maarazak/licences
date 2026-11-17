import "server-only";
import { and, desc, eq, gt, isNull, like, lt, sql } from "drizzle-orm";
import { affected, db, schema } from "@/server/db";
import type { User } from "@/server/db/schema";
import { adminCodeMail, loginCodeMail } from "@/server/mail/templates";
import { sendMail } from "@/server/mail/mailer";
import { getLang } from "@/server/i18n";
import { hmac, newId, randomCode, randomToken, safeEqual, sha256 } from "@/server/security/crypto";

const CODE_TTL_MS = 10 * 60_000;
const MAX_ATTEMPTS = 5;

const codeHash = (email: string, code: string) => hmac("login-code", `${email}:${code}`);

/**
 * Crée un code de connexion et l'envoie. Les codes précédents de la même
 * adresse sont invalidés : un seul code est valable à la fois.
 */
export async function issueLoginCode(email: string, ip: string): Promise<boolean> {
  const code = randomCode();
  const now = new Date();
  await db()
    .update(schema.loginCodes)
    .set({ consumedAt: now })
    .where(and(eq(schema.loginCodes.email, email), isNull(schema.loginCodes.consumedAt)));
  await db()
    .insert(schema.loginCodes)
    .values({ id: newId(), email, codeHash: codeHash(email, code), expiresAt: new Date(now.getTime() + CODE_TTL_MS), ip, createdAt: now });
  return sendMail(loginCodeMail(email, code, await getLang()));
}

export type CodeCheck = "ok" | "invalid" | "expired" | "locked";

/**
 * Vérifie le code actif d'une clé : usage unique, 5 essais au plus (comptés avant la comparaison, de façon atomique),
 * comparaison à temps constant. Partagé par la connexion client et la double authentification administrateur.
 */
async function checkCode(key: string, expectedHash: string): Promise<CodeCheck> {
  const now = new Date();
  const [row] = await db()
    .select()
    .from(schema.loginCodes)
    .where(and(eq(schema.loginCodes.email, key), isNull(schema.loginCodes.consumedAt), gt(schema.loginCodes.expiresAt, now)))
    .orderBy(desc(schema.loginCodes.createdAt))
    .limit(1);
  if (!row) return "expired";

  const counted = await db()
    .update(schema.loginCodes)
    .set({ attempts: sql`${schema.loginCodes.attempts} + 1` })
    .where(and(eq(schema.loginCodes.id, row.id), lt(schema.loginCodes.attempts, MAX_ATTEMPTS), isNull(schema.loginCodes.consumedAt)));
  if (affected(counted) !== 1) return "locked";

  if (!safeEqual(row.codeHash, expectedHash)) return "invalid";

  const consumed = await db()
    .update(schema.loginCodes)
    .set({ consumedAt: now })
    .where(and(eq(schema.loginCodes.id, row.id), isNull(schema.loginCodes.consumedAt)));
  if (affected(consumed) !== 1) return "expired";
  return "ok";
}

export type VerifyResult = { ok: true; user: User } | { ok: false; reason: "invalid" | "expired" | "locked" | "blocked" };

/** Vérifie un code de connexion client : usage unique, 5 essais au plus. */
export async function verifyLoginCode(email: string, code: string): Promise<VerifyResult> {
  const check = await checkCode(email, codeHash(email, code));
  if (check !== "ok") return { ok: false, reason: check };
  const user = await findOrCreateUser(email);
  if (user.isBlocked) return { ok: false, reason: "blocked" };
  return { ok: true, user };
}

// ─── Double authentification des administrateurs (code envoyé par e-mail après le mot de passe) ───

/**
 * Clé du défi : administrateur + empreinte d'un jeton secret gardé dans un cookie. Sans ce cookie (obtenu seulement après
 * un mot de passe correct), on ne peut pas essayer de codes : la 2FA ne peut pas être attaquée sans connaître le mot de passe.
 */
const adminKey = (userId: string, token: string) => `admin-2fa:${userId}:${sha256(token)}`;
const adminCodeHash = (key: string, code: string) => hmac("admin-2fa-code", `${key}:${code}`);

/** Crée un défi 2FA (un seul actif par administrateur) et envoie le code à son adresse e-mail. */
export async function issueAdminChallenge(user: Pick<User, "id" | "email">, ip: string): Promise<{ token: string; sent: boolean }> {
  const token = randomToken(32);
  const key = adminKey(user.id, token);
  const code = randomCode();
  const now = new Date();
  await db()
    .update(schema.loginCodes)
    .set({ consumedAt: now })
    .where(and(like(schema.loginCodes.email, `admin-2fa:${user.id}:%`), isNull(schema.loginCodes.consumedAt)));
  await db()
    .insert(schema.loginCodes)
    .values({ id: newId(), email: key, codeHash: adminCodeHash(key, code), expiresAt: new Date(now.getTime() + CODE_TTL_MS), ip, createdAt: now });
  const sent = await sendMail(adminCodeMail(user.email, code));
  return { token, sent };
}

/** Vérifie le code 2FA d'un administrateur pour le défi identifié par son jeton. */
export async function verifyAdminChallenge(userId: string, token: string, code: string): Promise<CodeCheck> {
  const key = adminKey(userId, token);
  return checkCode(key, adminCodeHash(key, code));
}

/** Les comptes créés par la connexion par code sont toujours des clients ; les administrateurs viennent du seeder. */
async function findOrCreateUser(email: string): Promise<User> {
  const now = new Date();
  await db()
    .insert(schema.users)
    .values({ id: newId(), email, role: "customer", lang: await getLang(), createdAt: now, lastLoginAt: now })
    .onDuplicateKeyUpdate({ set: { lastLoginAt: now } });
  const [user] = await db().select().from(schema.users).where(eq(schema.users.email, email)).limit(1);
  if (!user) throw new Error("Compte introuvable après création");
  return user;
}

export async function purgeLoginCodes(): Promise<void> {
  await db().delete(schema.loginCodes).where(lt(schema.loginCodes.expiresAt, new Date(Date.now() - 86_400_000)));
}
