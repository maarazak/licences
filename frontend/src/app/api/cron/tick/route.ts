import { NextResponse } from "next/server";
import { env } from "@/server/env";
import { safeEqual, sha256 } from "@/server/security/crypto";
import { runTick } from "@/server/services/scheduler";

export const dynamic = "force-dynamic";
export const maxDuration = 300;

/**
 * Point d'entrée externe de la tâche planifiée (si le planificateur interne est désactivé ou que l'hébergeur
 * n'a pas de processus permanent) :
 *   curl -X POST -H "Authorization: Bearer $CRON_SECRET" https://…/api/cron/tick
 */
async function handle(request: Request): Promise<NextResponse> {
  const header = request.headers.get("authorization") ?? "";
  const token = header.startsWith("Bearer ") ? header.slice(7) : "";
  // Comparaison à temps constant sur des empreintes de même longueur.
  if (!token || !safeEqual(sha256(token), sha256(env().CRON_SECRET))) return NextResponse.json({ error: "unauthorized" }, { status: 401 });
  return NextResponse.json(await runTick());
}

export const POST = handle;
/** Vercel Cron appelle en GET avec le même en-tête Authorization. */
export const GET = handle;
