import { PageLoader } from "@/components/page-loader";
import { getI18n } from "@/server/i18n";

export default async function Loading() {
  const { t } = await getI18n();
  return <PageLoader label={t("Chargement…")} />;
}
