import Link from "next/link";
import { SiteFooter, SiteHeader } from "@/components/site-chrome";
import { currentUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";

/** 404 globale (adresse inconnue, y compris /admin pour un non-administrateur) : même habillage que le site. */
export default async function GlobalNotFound() {
  const [{ t }, user] = await Promise.all([getI18n(), currentUser().catch(() => null)]);
  return (
    <>
      <SiteHeader signedIn={!!user} email={user?.email} notice="" />
      <main id="contenu" className="container-page py-20 text-center">
        <p className="eyebrow text-accent-ink">{t("Erreur 404")}</p>
        <h1 className="h1 mt-2">{t("Page introuvable")}</h1>
        <p className="lead mx-auto mt-3 max-w-md">{t("Cette page n'existe pas ou n'est plus disponible.")}</p>
        <Link href="/boutique" className="btn btn-brand mt-6">
          {t("Voir la boutique")}
        </Link>
      </main>
      <SiteFooter />
    </>
  );
}
