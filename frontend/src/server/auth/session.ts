import "server-only";
import { cache } from "react";
import { cookies } from "next/headers";
import { notFound, redirect } from "next/navigation";
import { and, eq, gt, lt } from "drizzle-orm";
import { db, schema } from "@/server/db";
import type { User } from "@/server/db/schema";
import { isProd } from "@/server/env";
import { newId, randomToken, sha256 } from "@/server/security/crypto";
import { clientIp, userAgent } from "@/server/security/request";

const SESSION_DAYS = 30;
/** Les sessions administrateur sont plus courtes. */
const ADMIN_SESSION_HOURS = 12;

/** Le préfixe __Host- impose Secure, Path=/ et l'absence de Domain. */
function cookieName(): string {
  return isProd() ? "__Host-session" : "session";
}

export async function createSession(user: Pick<User, "id" | "role">): Promise<void> {
  const token = randomToken(32);
  const ms = user.role === "admin" ? ADMIN_SESSION_HOURS * 3600_000 : SESSION_DAYS * 86_400_000;
  const expiresAt = new Date(Date.now() + ms);
  await db()
    .insert(schema.sessions)
    .values({ id: newId(), tokenHash: sha256(token), userId: user.id, expiresAt, ip: await clientIp(), userAgent: await userAgent(), createdAt: new Date() });
  (await cookies()).set(cookieName(), token, { httpOnly: true, secure: isProd(), sameSite: "lax", path: "/", expires: expiresAt });
}

export async function destroySession(): Promise<void> {
  const jar = await cookies();
  const token = jar.get(cookieName())?.value;
  if (token) await db().delete(schema.sessions).where(eq(schema.sessions.tokenHash, sha256(token)));
  // Écrasement explicite : un cookie __Host- ne peut être supprimé que par un Set-Cookie portant les mêmes attributs (Secure, Path=/).
  jar.set(cookieName(), "", { httpOnly: true, secure: isProd(), sameSite: "lax", path: "/", maxAge: 0 });
}

export async function destroyAllSessions(userId: string): Promise<void> {
  await db().delete(schema.sessions).where(eq(schema.sessions.userId, userId));
}

export async function purgeExpiredSessions(): Promise<void> {
  await db().delete(schema.sessions).where(lt(schema.sessions.expiresAt, new Date()));
}

/** Utilisateur connecté, ou null. Vérifié en base à chaque requête (blocage et rôle pris en compte immédiatement). */
export const currentUser = cache(async (): Promise<User | null> => {
  const token = (await cookies()).get(cookieName())?.value;
  if (!token || token.length > 200) return null;
  const rows = await db()
    .select({ user: schema.users })
    .from(schema.sessions)
    .innerJoin(schema.users, eq(schema.users.id, schema.sessions.userId))
    .where(and(eq(schema.sessions.tokenHash, sha256(token)), gt(schema.sessions.expiresAt, new Date())))
    .limit(1);
  const user = rows[0]?.user;
  if (!user || user.isBlocked) return null;
  return user;
});

/** Administrateur : rôle `admin` en base (créé par le seeder `npm run db:seed`, jamais depuis le site). */
export function isAdmin(user: Pick<User, "role" | "email"> | null): boolean {
  return !!user && user.role === "admin";
}

/** Pour les pages : redirige vers la connexion si nécessaire. */
export async function requireUser(nextPath: string): Promise<User> {
  const user = await currentUser();
  if (!user) redirect(`/connexion?next=${encodeURIComponent(nextPath)}`);
  return user;
}

/**
 * Pour les pages du backoffice. Un visiteur non administrateur (connecté ou non) reçoit une 404 : le backoffice
 * n'est lié depuis aucune page du site et ne révèle pas son existence. Les administrateurs y accèdent en
 * saisissant directement son adresse après s'être connectés.
 */
export async function requireAdmin(_nextPath = "/admin"): Promise<User> {
  void _nextPath;
  const user = await currentUser();
  if (!user || !isAdmin(user)) notFound();
  return user;
}

export class AuthError extends Error {}

/** Pour les actions serveur : lève une erreur au lieu de rediriger. */
export async function actionUser(): Promise<User> {
  const user = await currentUser();
  if (!user) throw new AuthError("Votre session a expiré. Reconnectez-vous.");
  return user;
}

export async function actionAdmin(): Promise<User> {
  const user = await actionUser();
  if (!isAdmin(user)) throw new AuthError("Action réservée aux administrateurs.");
  return user;
}
