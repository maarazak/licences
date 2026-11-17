import { formatDateTime } from "@/lib/format";
import { getI18n } from "@/server/i18n";
import type { TicketMessage } from "@/server/db/schema";

/** Fil de messages d'une demande. `staffView` : vue du backoffice (en français), où le personnel est « vous ». */
export async function TicketThread({ messages, staffView = false }: { messages: TicketMessage[]; staffView?: boolean }) {
  const { t } = await getI18n();
  const label = (staff: boolean) => (staff ? (staffView ? "Assistance" : t("Assistance")) : staffView ? "Client" : t("Vous"));
  return (
    <ol className="space-y-3">
      {messages.map((message) => {
        const mine = message.isStaff === staffView;
        return (
          <li key={message.id} className={`max-w-[85%] rounded-2xl border p-4 ${mine ? "ml-auto border-brand/20 bg-brand-soft" : "border-line bg-paper shadow-sm"}`}>
            <p className="mb-1 text-xs font-bold text-muted">
              {label(message.isStaff)} · {formatDateTime(message.createdAt)}
            </p>
            <p className="prose-plain">{message.body}</p>
          </li>
        );
      })}
    </ol>
  );
}
