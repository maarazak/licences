"use client";

import { useRouter } from "next/navigation";
import { startTransition } from "react";
import { useI18n } from "@/components/i18n";

/** Écran d'erreur : aucun détail technique n'est montré au visiteur. */
export default function ErrorPage({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  const { t } = useI18n();
  const router = useRouter();
  return (
    <div className="container-page py-20 text-center">
      <h1 className="h1">{t("Une erreur est survenue")}</h1>
      <p className="lead mx-auto mt-3 max-w-md">{t("Réessayez ; si le problème continue, contactez l'assistance. Si vous veniez de payer, consultez « Mes achats » avant de recommencer.")}</p>
      <button type="button" onClick={() => startTransition(() => { router.refresh(); reset(); })} className="btn btn-brand mt-6">
        {t("Réessayer")}
      </button>
    </div>
  );
}
