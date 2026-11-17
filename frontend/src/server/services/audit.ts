import "server-only";
import { db, schema } from "@/server/db";
import type { User } from "@/server/db/schema";
import { newId } from "@/server/security/crypto";
import { clientIp } from "@/server/security/request";

/** Journal des actions du backoffice : qui a fait quoi, quand, depuis où. */
export async function audit(admin: Pick<User, "id" | "email">, action: string, target = "", details = ""): Promise<void> {
  // Écrit APRÈS l'action : un échec d'audit ne doit pas faire croire que l'action a échoué (l'administrateur la rejouerait).
  try {
    await db()
      .insert(schema.auditLogs)
      .values({ id: newId(), adminId: admin.id, adminEmail: admin.email, action, target: target.slice(0, 191), details: details.slice(0, 1000), ip: await clientIp(), createdAt: new Date() });
  } catch (error) {
    console.error("[audit] écriture impossible", action, target, error);
  }
}
