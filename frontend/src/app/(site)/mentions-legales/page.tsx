import type { Metadata } from "next";
import { LegalPage } from "@/components/legal";
import { SITE_NAME } from "@/lib/site";
import { getI18n } from "@/server/i18n";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Mentions légales") };
}

export default async function LegalNoticePage() {
  const { t } = await getI18n();
  return (
    <LegalPage
      title={t("Mentions légales")}
      updatedLabel={t("Dernière mise à jour")}
      updated={t("12 novembre 2026")}
      sections={[
        { title: t("Indépendance"), paragraphs: [t("{name} est une boutique indépendante. Les noms et logos des services proposés appartiennent à leurs propriétaires respectifs et ne sont utilisés que pour désigner le service vendu, sans partenariat officiel.", { name: SITE_NAME })] },
      ]}
    />
  );
}
