import "server-only";
import { sql } from "drizzle-orm";
import { db } from "@/server/db";
import { sha256 } from "./crypto";

export type RateLimitResult = { ok: boolean; retryAfterSeconds: number };

/**
 * Limiteur à fenêtre fixe, stocké en base (fonctionne avec plusieurs instances).
 * L'incrément est atomique : une seule requête SQL.
 */
export async function rateLimit(scope: string, subject: string, limit: number, windowSeconds: number): Promise<RateLimitResult> {
  const key = `${scope}:${sha256(subject).slice(0, 40)}`;
  const now = new Date();
  const resetAt = new Date(now.getTime() + windowSeconds * 1000);

  await db().execute(sql`
    INSERT INTO rate_limits (\`key\`, \`count\`, reset_at)
    VALUES (${key}, 1, ${resetAt})
    ON DUPLICATE KEY UPDATE
      \`count\` = IF(reset_at <= ${now}, 1, \`count\` + 1),
      reset_at = IF(reset_at <= ${now}, ${resetAt}, reset_at)
  `);

  const [rows] = (await db().execute(sql`SELECT \`count\` AS c, reset_at AS r FROM rate_limits WHERE \`key\` = ${key}`)) as unknown as [
    Array<{ c: number; r: Date | string }>,
  ];
  const row = rows[0];
  if (!row) return { ok: true, retryAfterSeconds: 0 };
  const reset = row.r instanceof Date ? row.r : new Date(`${String(row.r).replace(" ", "T")}Z`);
  const retryAfterSeconds = Math.max(1, Math.ceil((reset.getTime() - now.getTime()) / 1000));
  return { ok: Number(row.c) <= limit, retryAfterSeconds };
}

/** Libère un compteur consommé pour rien (ex. e-mail non parti) : la prochaine tentative repart de zéro. */
export async function releaseRateLimit(scope: string, subject: string): Promise<void> {
  const key = `${scope}:${sha256(subject).slice(0, 40)}`;
  await db().execute(sql`DELETE FROM rate_limits WHERE \`key\` = ${key}`);
}

/** Purge des compteurs expirés (appelée par la tâche planifiée). */
export async function purgeRateLimits(): Promise<void> {
  await db().execute(sql`DELETE FROM rate_limits WHERE reset_at <= ${new Date()}`);
}

export function waitMessage(seconds: number): string {
  if (seconds < 90) return `Trop de tentatives. Réessayez dans ${seconds} secondes.`;
  return `Trop de tentatives. Réessayez dans ${Math.ceil(seconds / 60)} minutes.`;
}
