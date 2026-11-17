import "server-only";
import { and, asc, desc, eq } from "drizzle-orm";
import { db, schema } from "@/server/db";
import type { Ticket, TicketMessage, User } from "@/server/db/schema";
import type { TicketStatus } from "@/lib/enums";
import { staffEmails } from "@/server/services/staff";
import { sendMail } from "@/server/mail/mailer";
import { adminAlertMail, ticketReplyMail } from "@/server/mail/templates";
import { newId, randomReference } from "@/server/security/crypto";
import { rateLimit } from "@/server/security/rate-limit";

const UUID = /^[0-9a-f-]{36}$/;

export async function createTicket(user: User, input: { subject: string; body: string; orderId: string | null }): Promise<Ticket> {
  const now = new Date();
  let orderId: string | null = null;
  if (input.orderId) {
    // Une demande ne peut être rattachée qu'à une commande du client lui-même.
    const [order] = await db()
      .select({ id: schema.orders.id })
      .from(schema.orders)
      .where(and(eq(schema.orders.id, input.orderId), eq(schema.orders.userId, user.id)))
      .limit(1);
    orderId = order?.id ?? null;
  }
  const ticket: Ticket = { id: newId(), reference: randomReference("AID", 6), userId: user.id, orderId, subject: input.subject, status: "open", createdAt: now, updatedAt: now };
  await db().transaction(async (tx) => {
    await tx.insert(schema.tickets).values(ticket);
    await tx.insert(schema.ticketMessages).values({ id: newId(), ticketId: ticket.id, authorId: user.id, isStaff: false, body: input.body, createdAt: now });
  });
  await notifyStaff(ticket, user.email);
  return ticket;
}

async function notifyStaff(ticket: Ticket, from: string): Promise<void> {
  // Une alerte par demande et par quart d'heure : les réponses en rafale d'un client n'inondent pas les administrateurs.
  if (!(await rateLimit("staff-notify", ticket.id, 1, 900)).ok) return;
  const to = await staffEmails();
  await Promise.all(to.map((a) => sendMail(adminAlertMail(a, `Demande d'assistance ${ticket.reference}`, [`De : ${from}`, `Objet : ${ticket.subject}`], `/admin/assistance/${ticket.id}`))));
}

export async function listUserTickets(userId: string): Promise<Ticket[]> {
  return db().select().from(schema.tickets).where(eq(schema.tickets.userId, userId)).orderBy(desc(schema.tickets.updatedAt)).limit(100);
}

export type TicketThread = { ticket: Ticket; messages: TicketMessage[]; orderReference: string | null; customerEmail: string; customerLang: "fr" | "en" };

async function thread(ticket: Ticket): Promise<TicketThread> {
  const messages = await db().select().from(schema.ticketMessages).where(eq(schema.ticketMessages.ticketId, ticket.id)).orderBy(asc(schema.ticketMessages.createdAt));
  const [order] = ticket.orderId ? await db().select({ reference: schema.orders.reference }).from(schema.orders).where(eq(schema.orders.id, ticket.orderId)).limit(1) : [];
  const [customer] = await db().select({ email: schema.users.email, lang: schema.users.lang }).from(schema.users).where(eq(schema.users.id, ticket.userId)).limit(1);
  return { ticket, messages, orderReference: order?.reference ?? null, customerEmail: customer?.email ?? "", customerLang: customer?.lang === "en" ? ("en" as const) : ("fr" as const) };
}

/** Fil d'un client : toujours filtré par propriétaire. */
export async function getUserTicket(userId: string, ticketId: string): Promise<TicketThread | null> {
  if (!UUID.test(ticketId)) return null;
  const [ticket] = await db()
    .select()
    .from(schema.tickets)
    .where(and(eq(schema.tickets.id, ticketId), eq(schema.tickets.userId, userId)))
    .limit(1);
  return ticket ? thread(ticket) : null;
}

export async function customerReply(user: User, ticketId: string, body: string): Promise<boolean> {
  const found = await getUserTicket(user.id, ticketId);
  if (!found || found.ticket.status === "closed") return false;
  const now = new Date();
  await db().insert(schema.ticketMessages).values({ id: newId(), ticketId, authorId: user.id, isStaff: false, body, createdAt: now });
  await db().update(schema.tickets).set({ status: "open", updatedAt: now }).where(eq(schema.tickets.id, ticketId));
  await notifyStaff(found.ticket, user.email);
  return true;
}

// ─── Backoffice ──────────────────────────────────────────────────────────────

export async function adminListTickets(status?: TicketStatus) {
  return db()
    .select({ ticket: schema.tickets, email: schema.users.email })
    .from(schema.tickets)
    .innerJoin(schema.users, eq(schema.users.id, schema.tickets.userId))
    .where(status ? eq(schema.tickets.status, status) : undefined)
    .orderBy(asc(schema.tickets.status), desc(schema.tickets.updatedAt))
    .limit(300);
}

export async function adminGetTicket(ticketId: string): Promise<TicketThread | null> {
  if (!UUID.test(ticketId)) return null;
  const [ticket] = await db().select().from(schema.tickets).where(eq(schema.tickets.id, ticketId)).limit(1);
  return ticket ? thread(ticket) : null;
}

export async function adminReply(admin: User, ticketId: string, body: string): Promise<boolean> {
  const found = await adminGetTicket(ticketId);
  if (!found) return false;
  const now = new Date();
  await db().insert(schema.ticketMessages).values({ id: newId(), ticketId, authorId: admin.id, isStaff: true, body, createdAt: now });
  await db().update(schema.tickets).set({ status: "answered", updatedAt: now }).where(eq(schema.tickets.id, ticketId));
  if (found.customerEmail) await sendMail(ticketReplyMail(found.customerEmail, found.ticket, found.customerLang));
  return true;
}

export async function adminSetTicketStatus(ticketId: string, status: TicketStatus): Promise<void> {
  await db().update(schema.tickets).set({ status, updatedAt: new Date() }).where(eq(schema.tickets.id, ticketId));
}
