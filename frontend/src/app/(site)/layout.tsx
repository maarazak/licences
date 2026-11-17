import { BottomNav, SiteFooter, SiteHeader } from "@/components/site-chrome";
import { currentUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";
import { getSettings } from "@/server/services/settings";

export default async function SiteLayout({ children }: { children: React.ReactNode }) {
  const [user, settings, { t }] = await Promise.all([currentUser(), getSettings(), getI18n()]);
  return (
    <>
      <a href="#contenu" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-paper focus:px-3 focus:py-2 focus:text-sm">
        {t("Aller au contenu")}
      </a>
      <SiteHeader signedIn={!!user} email={user?.email} notice={settings.notice} />
      <main id="contenu">{children}</main>
      <SiteFooter />
      <BottomNav />
    </>
  );
}
