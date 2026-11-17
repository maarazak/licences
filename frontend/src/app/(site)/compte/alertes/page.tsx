import type { Metadata } from "next";
import Link from "next/link";
import { EmptyState, PageHeader } from "@/components/ui";
import { formatDate } from "@/lib/format";
import { removeAlertAction } from "@/server/actions/shop";
import { requireUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";
import { listUserAlerts } from "@/server/services/alerts";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Mes alertes"), robots: { index: false } };
}

export default async function AlertsPage() {
  const user = await requireUser("/compte/alertes");
  const { t } = await getI18n();
  const alerts = await listUserAlerts(user.id);
  return (
    <div className="space-y-6">
      <PageHeader title={t("Mes alertes")} description={t("Un seul e-mail par offre, dès son retour en stock. Ensuite, l'alerte s'arrête.")} />
      {alerts.length ? (
        <ul className="space-y-2">
          {alerts.map(({ alert, product }) => (
            <li key={alert.id} className="card flex flex-wrap items-center justify-between gap-3 p-4">
              <div className="min-w-0">
                <Link href={`/boutique/${product.slug}`} className="h3 hover:underline">
                  {product.displayName?.trim() || product.name}
                </Link>
                <p className="text-xs text-muted">{t("Alerte créée le {date}", { date: formatDate(alert.createdAt) })}</p>
              </div>
              <form action={removeAlertAction}>
                <input type="hidden" name="alertId" value={alert.id} />
                <button type="submit" className="btn btn-soft btn-sm">
                  {t("Retirer l'alerte")}
                </button>
              </form>
            </li>
          ))}
        </ul>
      ) : (
        <EmptyState title={t("Aucune alerte en cours")} description={t("Sur une offre en rupture, cliquez sur « Prévenez-moi du retour ».")} action={{ href: "/boutique", label: t("Voir la boutique") }} />
      )}
    </div>
  );
}
