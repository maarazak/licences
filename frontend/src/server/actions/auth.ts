"use server";

import { redirect } from "next/navigation";
import type { ActionState } from "@/lib/action-state";
import { clearAdminChallengeCookie, readAdminChallengeCookie, setAdminChallengeCookie } from "@/server/auth/admin-2fa";
import { issueAdminChallenge, issueLoginCode, verifyAdminChallenge, verifyLoginCode } from "@/server/auth/otp";
import { verifyPassword } from "@/server/auth/password";
import { createSession, destroySession, isAdmin } from "@/server/auth/session";
import { db, schema } from "@/server/db";
import { eq } from "drizzle-orm";
import { rateLimit, waitMessage } from "@/server/security/rate-limit";
import { clientIp, safeNextPath } from "@/server/security/request";
import { codeSchema, emailSchema, firstError } from "@/server/validators";

/** Étape 1 : envoi du code. La réponse est identique que le compte existe ou non. */
export async function requestCodeAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  const parsed = emailSchema.safeParse(formData.get("email"));
  if (!parsed.success) return { error: firstError(parsed.error) };
  const email = parsed.data;
  const ip = await clientIp();

  const byIp = await rateLimit("login-request-ip", ip, 15, 3600);
  if (!byIp.ok) return { error: waitMessage(byIp.retryAfterSeconds) };
  // Par couple (e-mail, IP) : un tiers ne peut pas priver la victime de code depuis une autre adresse IP.
  const byEmail = await rateLimit("login-request-email", `${email}|${ip}`, 5, 900);
  if (!byEmail.ok) return { error: waitMessage(byEmail.retryAfterSeconds) };
  const byEmailDay = await rateLimit("login-request-email-day", email, 40, 86_400);
  if (!byEmailDay.ok) return { error: waitMessage(byEmailDay.retryAfterSeconds) };

  // Les administrateurs se connectent uniquement par mot de passe (formulaire dédié) : aucun code ne leur est envoyé.
  // La réponse reste identique pour ne pas révéler quelles adresses sont administrateurs.
  const [existing] = await db().select({ role: schema.users.role }).from(schema.users).where(eq(schema.users.email, email)).limit(1);
  if (existing?.role === "admin") return { step: "code", email };

  const sent = await issueLoginCode(email, ip);
  if (!sent) return { error: "L'e-mail n'a pas pu être envoyé. Réessayez dans un instant." };
  return { step: "code", email };
}

/** Étape 2 : vérification du code et ouverture de session. */
export async function verifyCodeAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  const emailParsed = emailSchema.safeParse(formData.get("email"));
  if (!emailParsed.success) return { error: "Session de connexion expirée. Recommencez." };
  const email = emailParsed.data;
  const codeParsed = codeSchema.safeParse(formData.get("code"));
  if (!codeParsed.success) return { step: "code", email, error: firstError(codeParsed.error) };

  const byIp = await rateLimit("login-verify-ip", await clientIp(), 30, 3600);
  if (!byIp.ok) return { step: "code", email, error: waitMessage(byIp.retryAfterSeconds) };
  // Pas de plafond par e-mail ici : chaque code est déjà limité à 5 essais, et un tiers ne doit pas pouvoir verrouiller
  // la connexion d'un compte en envoyant de faux codes. Plafond global contre les attaques réparties sur de nombreux comptes.
  const global = await rateLimit("login-verify-global", "*", 3000, 3600);
  if (!global.ok) return { step: "code", email, error: waitMessage(global.retryAfterSeconds) };

  const result = await verifyLoginCode(email, codeParsed.data);
  if (!result.ok) {
    if (result.reason === "blocked") return { error: "Ce compte est suspendu. Contactez l'assistance." };
    // Même réponse pour « incorrect », « expiré » et « aucun code actif » : on ne révèle pas si un code a été demandé.
    return { step: "code", email, error: "Code incorrect ou expiré. Vérifiez-le ou demandez-en un nouveau." };
  }

  // Défense en profondeur : un compte administrateur n'ouvre jamais de session par simple code e-mail.
  if (isAdmin(result.user)) return { step: "code", email, error: "Code incorrect ou expiré. Vérifiez-le ou demandez-en un nouveau." };

  await createSession(result.user);
  // Le site client ne mène jamais au backoffice : même un administrateur arrive sur son espace client.
  redirect(safeNextPath(formData.get("next"), "/compte/achats"));
}

export async function logoutAction(): Promise<void> {
  await destroySession();
  redirect("/");
}

/** Connexion du backoffice : e-mail + mot de passe, formulaire distinct de celui des clients. */
export async function adminLoginAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  const GENERIC = "E-mail ou mot de passe incorrect.";
  const parsed = emailSchema.safeParse(formData.get("email"));
  const password = formData.get("password");
  if (!parsed.success || typeof password !== "string" || password.length < 1 || password.length > 200) return { error: GENERIC };
  const email = parsed.data;
  const ip = await clientIp();

  const byIp = await rateLimit("admin-login-ip", ip, 10, 900);
  if (!byIp.ok) return { error: waitMessage(byIp.retryAfterSeconds) };
  const byEmail = await rateLimit("admin-login-email", email, 8, 900);
  if (!byEmail.ok) return { error: waitMessage(byEmail.retryAfterSeconds) };
  const byEmailDay = await rateLimit("admin-login-email-day", email, 30, 86_400);
  if (!byEmailDay.ok) return { error: waitMessage(byEmailDay.retryAfterSeconds) };

  const [user] = await db().select().from(schema.users).where(eq(schema.users.email, email)).limit(1);
  // Le calcul du hachage a toujours lieu (empreinte factice si le compte n'existe pas) : temps de réponse identique.
  const passwordOk = await verifyPassword(password, user?.passwordHash);
  if (!user || !passwordOk || !isAdmin(user) || user.isBlocked) return { error: GENERIC };

  // Mot de passe correct : deuxième facteur. Aucune session n'est ouverte avant la saisie du code reçu par e-mail.
  const bySend = await rateLimit("admin-2fa-send", user.id, 5, 900);
  if (!bySend.ok) return { error: waitMessage(bySend.retryAfterSeconds) };
  const challenge = await issueAdminChallenge(user, ip);
  if (!challenge.sent) return { error: "Le code de vérification n'a pas pu être envoyé par e-mail. Réessayez dans un instant." };
  await setAdminChallengeCookie(user.id, challenge.token);
  return { step: "code", email };
}

/** Connexion du backoffice, étape 2 : code reçu par e-mail. Ouvre la session seulement si le mot de passe a été validé avant. */
export async function adminVerifyCodeAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  const EXPIRED = "Vérification expirée ou trop d'essais. Reconnectez-vous.";
  const challenge = await readAdminChallengeCookie();
  if (!challenge) return { error: EXPIRED };
  const echoed = emailSchema.safeParse(formData.get("email"));
  const email = echoed.success ? echoed.data : undefined;
  const codeParsed = codeSchema.safeParse(formData.get("code"));
  if (!codeParsed.success) return { step: "code", email, error: firstError(codeParsed.error) };

  const byIp = await rateLimit("admin-2fa-verify-ip", await clientIp(), 20, 3600);
  if (!byIp.ok) return { step: "code", email, error: waitMessage(byIp.retryAfterSeconds) };

  const check = await verifyAdminChallenge(challenge.userId, challenge.token, codeParsed.data);
  if (check === "invalid") return { step: "code", email, error: "Code incorrect." };
  if (check !== "ok") {
    await clearAdminChallengeCookie();
    return { error: EXPIRED };
  }

  const [user] = await db().select().from(schema.users).where(eq(schema.users.id, challenge.userId)).limit(1);
  if (!user || !isAdmin(user) || user.isBlocked) {
    await clearAdminChallengeCookie();
    return { error: EXPIRED };
  }
  await clearAdminChallengeCookie();
  await db().update(schema.users).set({ lastLoginAt: new Date() }).where(eq(schema.users.id, user.id));
  await createSession(user);
  redirect("/admin");
}
