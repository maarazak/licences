import "server-only";
import { purgeLoginCodes } from "@/server/auth/otp";
import { purgeExpiredSessions } from "@/server/auth/session";
import { rateLimit, purgeRateLimits } from "@/server/security/rate-limit";
import { processStockAlerts } from "./alerts";
import { refreshSupplierBalance, syncCatalog } from "./catalog";
import { runOrderMaintenance } from "./orders";
import { processReminders } from "./reminders";
import { loadSettings, saveSettings } from "./settings";

/** Ne pas resynchroniser si une synchronisation (bouton du backoffice, autre instance) date de moins que ça. */
const MIN_SYNC_GAP_MS = 45_000;

/**
 * Tâche d'une minute : rattrape les paiements, reprend les livraisons, suit les activations, synchronise le
 * catalogue (prix, stock, états) vers notre base, envoie les alertes et relances, purge les données périmées.
 * Un verrou en base empêche deux exécutions simultanées (planificateur interne + cron externe, plusieurs instances).
 */
export async function runTick(): Promise<Record<string, unknown>> {
  const lock = await rateLimit("cron-tick", "global", 1, 55);
  if (!lock.ok) return { skipped: "exécution précédente en cours" };

  const report: Record<string, unknown> = {};
  const step = async (name: string, run: () => Promise<unknown>) => {
    try {
      report[name] = await run();
    } catch (error) {
      console.error(`[tâche planifiée] ${name} en échec`, error);
      report[name] = { error: error instanceof Error ? error.message : "erreur" };
    }
  };

  await step("orders", runOrderMaintenance);
  await step("catalog", async () => {
    const settings = await loadSettings();
    const last = Date.parse(settings.lastCatalogSyncAt);
    if (Number.isFinite(last) && Date.now() - last < MIN_SYNC_GAP_MS) return "à jour";
    try {
      return await syncCatalog();
    } catch (error) {
      // Mémorisé pour le tableau de bord ; le catalogue déjà en base reste utilisé tel quel.
      await saveSettings({ catalogSyncError: (error instanceof Error ? error.message : "Erreur inconnue").slice(0, 300) });
      throw error;
    }
  });
  await step("balance", async () => {
    await refreshSupplierBalance();
    return "ok";
  });
  await step("alerts", processStockAlerts);
  await step("reminders", processReminders);
  await step("purge", async () => {
    await Promise.all([purgeRateLimits(), purgeExpiredSessions(), purgeLoginCodes()]);
    return "ok";
  });
  return report;
}

const globalKey = Symbol.for("licences.scheduler");

/**
 * Planificateur interne : exécute `runTick` chaque minute tant que le serveur tourne (serveur permanent : VPS, Docker, `npm run dev`).
 * Désactivable avec DISABLE_INTERNAL_SCHEDULER=1 si une tâche externe appelle déjà /api/cron/tick.
 */
export function startScheduler(): void {
  const g = globalThis as Record<symbol, unknown>;
  if (g[globalKey]) return;
  g[globalKey] = true;
  const loop = async () => {
    try {
      await runTick();
    } catch (error) {
      console.error("[planificateur] échec", error);
    }
    setTimeout(loop, 60_000).unref?.();
  };
  setTimeout(loop, 5_000).unref?.();
  console.log("[planificateur] tâche planifiée active (toutes les minutes)");
}
