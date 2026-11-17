import "server-only";
import { and, eq } from "drizzle-orm";
import { db, schema } from "@/server/db";
import { env } from "@/server/env";

/** Destinataires des alertes internes : administrateurs actifs (en base) + adresse d'assistance éventuelle. */
export async function staffEmails(): Promise<string[]> {
  const rows = await db().select({ email: schema.users.email }).from(schema.users).where(and(eq(schema.users.role, "admin"), eq(schema.users.isBlocked, false)));
  return [...new Set([...rows.map((r) => r.email), env().SUPPORT_EMAIL].filter(Boolean))];
}
