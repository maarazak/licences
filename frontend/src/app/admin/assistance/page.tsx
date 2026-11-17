import type { Metadata } from "next";
import Link from "next/link";
import { EmptyState, PageHeader } from "@/components/ui";
import { TicketStatusBadge } from "@/components/status-badges";
import { TICKET_STATUSES, TICKET_STATUS_LABELS, type TicketStatus } from "@/lib/enums";
import { formatDateTime } from "@/lib/format";
import { adminListTickets } from "@/server/services/tickets";
import { requireAdmin } from "@/server/auth/session";

export const metadata: Metadata = { title: "Assistance" };

export default async function AdminTicketsPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  // Le layout ne suffit pas (il ne se ré-exécute pas à chaque navigation) : chaque page vérifie le droit elle-même.
  await requireAdmin("/admin");
  const params = await searchParams;
  const raw = Array.isArray(params.statut) ? (params.statut[0] ?? "") : (params.statut ?? "");
  const status = (TICKET_STATUSES as readonly string[]).includes(raw) ? (raw as TicketStatus) : undefined;
  const rows = await adminListTickets(status);

  return (
    <div className="space-y-6">
      <PageHeader title="Assistance" />
      <div className="flex flex-wrap gap-2" role="group" aria-label="Filtrer par statut">
        <Link href="/admin/assistance" aria-current={!status ? "page" : undefined} className={`btn btn-sm ${!status ? "btn-brand" : "btn-soft"}`}>
          Toutes
        </Link>
        {TICKET_STATUSES.map((s) => (
          <Link key={s} href={`/admin/assistance?statut=${s}`} aria-current={s === status ? "page" : undefined} className={`btn btn-sm ${s === status ? "btn-brand" : "btn-soft"}`}>
            {TICKET_STATUS_LABELS[s]}
          </Link>
        ))}
      </div>
      {rows.length ? (
        <ul className="space-y-2">
          {rows.map(({ ticket, email }) => (
            <li key={ticket.id}>
              <Link href={`/admin/assistance/${ticket.id}`} className="card flex flex-wrap items-center justify-between gap-3 p-4 transition-colors hover:border-ink">
                <div className="min-w-0">
                  <p className="h3 truncate">{ticket.subject}</p>
                  <p className="text-xs text-muted">
                    {ticket.reference} · {email} · {formatDateTime(ticket.updatedAt)}
                  </p>
                </div>
                <TicketStatusBadge status={ticket.status} />
              </Link>
            </li>
          ))}
        </ul>
      ) : (
        <EmptyState title="Aucune demande" description="Aucune demande d'assistance ne correspond à ce filtre." />
      )}
    </div>
  );
}
