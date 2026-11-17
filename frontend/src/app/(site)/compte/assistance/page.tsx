import { Plus } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { EmptyState, PageHeader } from "@/components/ui";
import { TicketStatusBadge } from "@/components/status-badges";
import { formatDateTime } from "@/lib/format";
import { requireUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";
import { listUserTickets } from "@/server/services/tickets";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Assistance"), robots: { index: false } };
}

export default async function TicketsPage() {
  const user = await requireUser("/compte/assistance");
  const { t } = await getI18n();
  const tickets = await listUserTickets(user.id);
  return (
    <div className="space-y-6">
      <PageHeader
        title={t("Assistance")}
        actions={
          <Link href="/compte/assistance/nouveau" className="btn btn-brand">
            <Plus className="size-4" aria-hidden="true" />
            {t("Nouvelle demande")}
          </Link>
        }
      />
      {tickets.length ? (
        <ul className="space-y-2">
          {tickets.map((ticket) => (
            <li key={ticket.id}>
              <Link href={`/compte/assistance/${ticket.id}`} className="card flex flex-wrap items-center justify-between gap-3 p-4 transition duration-300 hover:-translate-y-0.5 hover:shadow-pop">
                <div className="min-w-0">
                  <p className="h3 truncate">{ticket.subject}</p>
                  <p className="text-xs text-muted">
                    {ticket.reference} · {t("mise à jour le {date}", { date: formatDateTime(ticket.updatedAt) })}
                  </p>
                </div>
                <TicketStatusBadge status={ticket.status} />
              </Link>
            </li>
          ))}
        </ul>
      ) : (
        <EmptyState title={t("Aucune demande pour l'instant")} description={t("Une question sur une commande ou un accès ? Ouvrez une demande, elle sera suivie ici.")} />
      )}
    </div>
  );
}
