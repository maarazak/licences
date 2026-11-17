import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ActionForm, SubmitButton } from "@/components/forms";
import { TicketThread } from "@/components/ticket-thread";
import { TicketStatusBadge } from "@/components/status-badges";
import { replyTicketAction } from "@/server/actions/shop";
import { requireUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";
import { getUserTicket } from "@/server/services/tickets";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Demande d'assistance"), robots: { index: false } };
}

export default async function TicketPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const user = await requireUser(`/compte/assistance/${id}`);
  const { t } = await getI18n();
  const found = await getUserTicket(user.id, id);
  if (!found) notFound();
  const { ticket, messages, orderReference } = found;

  return (
    <div className="max-w-3xl space-y-6">
      <nav aria-label={t("Fil d'Ariane")} className="text-xs font-medium text-muted">
        <Link href="/compte/assistance" className="hover:text-ink">
          {t("Assistance")}
        </Link>{" "}
        / <span className="text-ink">{ticket.reference}</span>
      </nav>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="h2">{ticket.subject}</h1>
        <TicketStatusBadge status={ticket.status} />
      </div>
      {ticket.orderId && orderReference ? (
        <p className="text-sm text-muted">
          {t("Commande concernée")} :{" "}
          <Link href={`/compte/achats/${ticket.orderId}`} className="link">
            {orderReference}
          </Link>
        </p>
      ) : null}
      <TicketThread messages={messages} />
      {ticket.status === "closed" ? (
        <p className="alert alert-info">{t("Cette demande est clôturée. Ouvrez une nouvelle demande si besoin.")}</p>
      ) : (
        <ActionForm action={replyTicketAction} resetOnSuccess className="card space-y-4 p-5">
          <input type="hidden" name="ticketId" value={ticket.id} />
          <div>
            <label htmlFor="body" className="label">
              {t("Votre réponse")}
            </label>
            <textarea id="body" name="body" required minLength={2} maxLength={4000} rows={4} className="input" />
          </div>
          <SubmitButton className="btn btn-brand">{t("Envoyer")}</SubmitButton>
        </ActionForm>
      )}
    </div>
  );
}
