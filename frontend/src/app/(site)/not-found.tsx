import Link from "next/link";
import { getI18n } from "@/server/i18n";

export default async function NotFound() {
  const { t } = await getI18n();
  return (
    <div className="container-page py-20 text-center">
      <p className="eyebrow text-accent-ink">{t("Erreur 404")}</p>
      <h1 className="h1 mt-2">{t("Page introuvable")}</h1>
      <p className="lead mx-auto mt-3 max-w-md">{t("Cette page n'existe pas ou n'est plus disponible.")}</p>
      <Link href="/boutique" className="btn btn-brand mt-6">
        {t("Voir la boutique")}
      </Link>
    </div>
  );
}
