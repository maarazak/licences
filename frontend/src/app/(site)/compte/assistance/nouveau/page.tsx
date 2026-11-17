import type { Metadata } from "next";
import Link from "next/link";
import { ActionForm, SubmitButton } from "@/components/forms";
import { PageHeader } from "@/components/ui";
import { createTicketAction } from "@/server/actions/shop";
import { requireUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";
import { listUserOrders } from "@/server/services/orders";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Nouvelle demande"), robots: { index: false } };
}

export default async function NewTicketPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const params = await searchParams;
  const user = await requireUser("/compte/assistance/nouveau");
  const { t } = await getI18n();
  const orders = await listUserOrders(user.id);
  const wanted = Array.isArray(params.commande) ? (params.commande[0] ?? "") : (params.commande ?? "");
  const selected = orders.some((o) => o.id === wanted) ? wanted : "";

  return (
    <div className="max-w-2xl space-y-6">
      <nav aria-label={t("Fil d'Ariane")} className="text-xs font-medium text-muted">
        <Link href="/compte/assistance" className="hover:text-ink">
          {t("Assistance")}
        </Link>{" "}
        / <span className="text-ink">{t("Nouvelle demande")}</span>
      </nav>
      <PageHeader title={t("Nouvelle demande")} />
      <ActionForm action={createTicketAction} className="card space-y-4 p-5">
        <div>
          <label htmlFor="orderId" className="label">
            {t("Commande concernée")}
          </label>
          <select id="orderId" name="orderId" defaultValue={selected} className="input">
            <option value="">{t("Aucune (question générale)")}</option>
            {orders.map((order) => (
              <option key={order.id} value={order.id}>
                {order.reference} — {order.productName}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label htmlFor="subject" className="label">
            {t("Objet")}
          </label>
          <input id="subject" name="subject" required minLength={3} maxLength={200} className="input" />
        </div>
        <div>
          <label htmlFor="body" className="label">
            {t("Votre message")}
          </label>
          <textarea id="body" name="body" required minLength={10} maxLength={4000} rows={7} className="input" />
          <p className="hint">{t("N'indiquez jamais de mot de passe ni de code de connexion.")}</p>
        </div>
        <SubmitButton className="btn btn-brand">{t("Envoyer la demande")}</SubmitButton>
      </ActionForm>
    </div>
  );
}
