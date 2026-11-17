import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ActionButton, ActionForm, SubmitButton } from "@/components/forms";
import { TicketThread } from "@/components/ticket-thread";
import { TicketStatusBadge } from "@/components/status-badges";
import { adminReplyTicketAction, setTicketStatusAction } from "@/server/actions/admin";
import { adminGetTicket } from "@/server/services/tickets";
import { requireAdmin } from "@/server/auth/session";

export const metadata: Metadata = { title: "Demande" };

export default async function AdminTicketPage({ params }: { params: Promise<{ id: string }> }) {
  // Le layout ne suffit pas (il ne se ré-exécute pas à chaque navigation) : chaque page vérifie le droit elle-même.
  await requireAdmin("/admin");
  const { id } = await params;
  const found = await adminGetTicket(id);
  if (!found) notFound();
  const { ticket, messages, orderReference, customerEmail } = found;

  return (
    <div className="max-w-3xl space-y-6">
      <nav aria-label="Fil d'Ariane" className="text-xs text-muted">
        <Link href="/admin/assistance" className="hover:text-ink">
          Assistance
        </Link>{" "}
        / <span className="text-ink">{ticket.reference}</span>
      </nav>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="h2">{ticket.subject}</h1>
        <TicketStatusBadge status={ticket.status} />
      </div>
      <p className="text-sm text-muted">
        Client : <span className="text-ink [overflow-wrap:anywhere]">{customerEmail}</span>
        {ticket.orderId && orderReference ? (
          <>
            {" · "}Commande :{" "}
            <Link href={`/admin/commandes/${ticket.orderId}`} className="link">
              {orderReference}
            </Link>
          </>
        ) : null}
      </p>
      <TicketThread messages={messages} staffView />
      <ActionForm action={adminReplyTicketAction} resetOnSuccess className="card space-y-4 p-5">
        <input type="hidden" name="ticketId" value={ticket.id} />
        <div>
          <label htmlFor="body" className="label">
            Répondre au client
          </label>
          <textarea id="body" name="body" required minLength={2} maxLength={4000} rows={5} className="input" />
          <p className="hint">Le client reçoit un e-mail l&apos;invitant à lire la réponse dans son espace.</p>
        </div>
        <SubmitButton className="btn btn-brand">Envoyer la réponse</SubmitButton>
      </ActionForm>
      {ticket.status === "closed" ? (
        <ActionButton action={setTicketStatusAction} fields={{ ticketId: ticket.id, status: "open" }}>
          Rouvrir la demande
        </ActionButton>
      ) : (
        <ActionButton action={setTicketStatusAction} fields={{ ticketId: ticket.id, status: "closed" }} confirm="Clôturer cette demande ?">
          Clôturer la demande
        </ActionButton>
      )}
    </div>
  );
}
