import { ORDER_STATUS_ADMIN_LABELS, ORDER_STATUS_LABELS, ORDER_STATUS_TONES, TICKET_STATUS_LABELS, TICKET_STATUS_TONES, type OrderStatus, type TicketStatus } from "@/lib/enums";
import { getI18n } from "@/server/i18n";
import { Badge } from "./ui";

/** Le backoffice reste en français ; côté client, le libellé suit la langue du visiteur. */
export async function OrderStatusBadge({ status, admin = false }: { status: OrderStatus; admin?: boolean }) {
  const { t } = await getI18n();
  return <Badge tone={ORDER_STATUS_TONES[status]}>{admin ? ORDER_STATUS_ADMIN_LABELS[status] : t(ORDER_STATUS_LABELS[status])}</Badge>;
}

export async function TicketStatusBadge({ status }: { status: TicketStatus }) {
  const { t } = await getI18n();
  return <Badge tone={TICKET_STATUS_TONES[status]}>{t(TICKET_STATUS_LABELS[status])}</Badge>;
}

