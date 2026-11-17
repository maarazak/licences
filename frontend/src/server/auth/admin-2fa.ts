import "server-only";
import { cookies } from "next/headers";
import { isProd } from "@/server/env";

const TTL_SECONDS = 10 * 60;

/** Cookie de défi : prouve qu'un mot de passe administrateur correct vient d'être saisi sur ce navigateur. */
function cookieName(): string {
  return isProd() ? "__Host-admin2fa" : "admin2fa";
}

export async function setAdminChallengeCookie(userId: string, token: string): Promise<void> {
  (await cookies()).set(cookieName(), `${userId}.${token}`, { httpOnly: true, secure: isProd(), sameSite: "strict", path: "/", maxAge: TTL_SECONDS });
}

export async function readAdminChallengeCookie(): Promise<{ userId: string; token: string } | null> {
  const value = (await cookies()).get(cookieName())?.value ?? "";
  const match = /^([0-9a-f-]{36})\.([A-Za-z0-9_-]{20,100})$/.exec(value);
  return match ? { userId: match[1], token: match[2] } : null;
}

export async function clearAdminChallengeCookie(): Promise<void> {
  (await cookies()).set(cookieName(), "", { httpOnly: true, secure: isProd(), sameSite: "strict", path: "/", maxAge: 0 });
}
