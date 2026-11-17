import Link from "next/link";
import { ActionButton } from "@/components/forms";
import { PageHeader, Stat } from "@/components/ui";
import { formatDateTime, formatUsdCents, formatXof } from "@/lib/format";
import { syncCatalogAction } from "@/server/actions/admin";
import { dashboardStats } from "@/server/services/admin";
import { catalogCounts, isCatalogSyncStale } from "@/server/services/catalog";
import { getSettings } from "@/server/services/settings";
import { requireAdmin } from "@/server/auth/session";

export default async function AdminDashboardPage() {
  // Le layout ne suffit pas (il ne se ré-exécute pas à chaque navigation) : chaque page vérifie le droit elle-même.
  await requireAdmin("/admin");
  const [stats, counts, settings] = await Promise.all([dashboardStats(), catalogCounts(), getSettings()]);
  const lastSync = settings.lastCatalogSyncAt ? formatDateTime(new Date(settings.lastCatalogSyncAt)) : "jamais";
  const syncStale = isCatalogSyncStale(settings.lastCatalogSyncAt);
  const inProgress = stats.counts.paid + stats.counts.processing + stats.counts.activation_awaiting;

  return (
    <div className="space-y-8">
      <PageHeader title="Tableau de bord" />

      {stats.counts.failed ? (
        <div className="alert alert-warning flex flex-wrap items-center justify-between gap-3">
          <span>
            <strong>{stats.counts.failed}</strong> commande(s) payée(s) non livrée(s) attendent une décision.
          </span>
          <Link href="/admin/commandes?filtre=a-traiter" className="btn btn-soft btn-sm">
            Traiter
          </Link>
        </div>
      ) : null}
      {settings.supplierBalanceError ? <p className="alert alert-error">Fournisseur injoignable : {settings.supplierBalanceError}. Le solde affiché date de la dernière lecture réussie.</p> : null}

      <section>
        <h2 className="h3 mb-3">Ventes livrées</h2>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <Stat label="24 dernières heures" value={formatXof(stats.today.total)} hint={`${stats.today.orders} commande(s) · marge ${formatXof(stats.today.margin)}`} />
          <Stat label="7 derniers jours" value={formatXof(stats.week.total)} hint={`${stats.week.orders} commande(s) · marge ${formatXof(stats.week.margin)}`} />
          <Stat label="30 derniers jours" value={formatXof(stats.month.total)} hint={`${stats.month.orders} commande(s) · marge ${formatXof(stats.month.margin)}`} />
        </div>
      </section>

      <section>
        <h2 className="h3 mb-3">Activité</h2>
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <Stat label="Solde fournisseur" value={settings.supplierBalanceAt ? formatUsdCents(settings.supplierBalanceCents) : "—"} hint={settings.supplierBalanceAt ? `Lu le ${formatDateTime(new Date(settings.supplierBalanceAt))} · rechargé dans le bot Telegram` : "Rechargé dans le bot Telegram"} />
          <Stat label="Commandes en cours" value={String(inProgress)} href="/admin/commandes?filtre=en-cours" />
          <Stat label="Demandes ouvertes" value={String(stats.openTickets)} href="/admin/assistance" />
          <Stat label="Clients" value={String(stats.customers)} href="/admin/clients" />
        </div>
      </section>

      <section className="card space-y-3 p-5">
        <h2 className="h3">Catalogue</h2>
        <p className="text-sm text-muted">
          {counts.published} produit(s) publié(s) sur {counts.total} · {counts.sellable} vendable(s) chez le fournisseur · dernière synchronisation : {lastSync}
        </p>
        <p className="text-sm text-muted">Synchronisation automatique chaque minute : prix, stock et état sont recopiés du fournisseur dans notre base ; les visiteurs ne voient que notre base.</p>
        {settings.catalogSyncError ? <p className="alert alert-error">Dernière synchronisation en échec : {settings.catalogSyncError}. Le catalogue enregistré reste en vente.</p> : null}
        {!settings.catalogSyncError && syncStale ? <p className="alert alert-warning">Aucune synchronisation depuis plus de 5 minutes : la tâche planifiée ne tourne pas. Vérifiez que le serveur est démarré ou que le cron appelle /api/cron/tick.</p> : null}
        <ActionButton action={syncCatalogAction} fields={{}} className="btn btn-brand btn-sm" busy="Synchronisation du catalogue…">
          Synchroniser maintenant
        </ActionButton>
      </section>
    </div>
  );
}
