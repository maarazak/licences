/** Valide la configuration au démarrage du serveur (en production, une clé manquante empêche le démarrage) puis lance la tâche planifiée. */
export function register(): void {
  if (process.env.NEXT_RUNTIME !== "nodejs") return;
  // Import différé : les modules serveur ne doivent pas être chargés côté Edge.
  void import("@/server/env").then(async ({ env }) => {
    env();
    if (process.env.NEXT_PHASE === "phase-production-build" || process.env.DISABLE_INTERNAL_SCHEDULER === "1") return;
    (await import("@/server/services/scheduler")).startScheduler();
  });
}
