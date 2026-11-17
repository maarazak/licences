"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";

/** Recharge les données de la page à intervalle régulier (suivi d'une commande en cours). */
export function AutoRefresh({ seconds = 5, maxMinutes = 10 }: { seconds?: number; maxMinutes?: number }) {
  const router = useRouter();
  useEffect(() => {
    const started = Date.now();
    const timer = setInterval(() => {
      if (Date.now() - started > maxMinutes * 60_000) clearInterval(timer);
      else if (document.visibilityState === "visible") router.refresh();
    }, seconds * 1000);
    return () => clearInterval(timer);
  }, [router, seconds, maxMinutes]);
  return null;
}
