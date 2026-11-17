import { Wallet } from "lucide-react";
import type { Metadata } from "next";
import { EmptyState, PageHeader } from "@/components/ui";
import { WalletDepositForm } from "@/components/wallet-deposit-form";
import { formatDateTime, formatXof } from "@/lib/format";
import { requireUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";
import { listWalletEntries } from "@/server/services/wallet";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Mon solde"), robots: { index: false } };
}

export default async function WalletPage() {
  const user = await requireUser("/compte/solde");
  const { t } = await getI18n();
  const entries = await listWalletEntries(user.id);
  const fullName = [user.firstName, user.lastName].filter(Boolean).join(" ");

  return (
    <div className="space-y-6">
      <PageHeader title={t("Mon solde")} />
      <div className="card relative overflow-hidden p-6">
        <div aria-hidden="true" className="absolute -top-12 -right-12 size-44 rounded-full bg-brand-bright/15 blur-2xl" />
        <div className="relative flex items-start gap-4">
          <span className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-bright to-brand text-white">
            <Wallet className="size-6" aria-hidden="true" />
          </span>
          <div>
            <p className="eyebrow">{t("Solde disponible")}</p>
            <p className="mt-1 font-display text-4xl font-extrabold tracking-tight">{formatXof(user.balanceXof)}</p>
            <p className="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
              {t("Votre solde vous permet de payer instantanément vos commandes sans passer par le prestataire à chaque achat. Vous pouvez le recharger par Mobile Money ci-dessous.")}
            </p>
          </div>
        </div>
      </div>

      <WalletDepositForm
        defaults={{
          fullName,
          phone: user.phone ?? undefined,
          country: user.country ?? undefined,
        }}
      />

      <h2 className="h2">{t("Historique")}</h2>
      {entries.length ? (
        <div className="table-wrap">
          <table className="table">
            <caption className="sr-only">{t("Mouvements du solde")}</caption>
            <thead>
              <tr>
                <th scope="col">{t("Date")}</th>
                <th scope="col">{t("Mouvement")}</th>
                <th scope="col" className="text-right">
                  {t("Montant")}
                </th>
                <th scope="col" className="text-right">
                  {t("Solde")}
                </th>
              </tr>
            </thead>
            <tbody>
              {entries.map((entry) => (
                <tr key={entry.id}>
                  <td className="whitespace-nowrap text-muted">{formatDateTime(entry.createdAt)}</td>
                  <td>
                    {entry.label
                      .replace(/^Avoir — commande (.+)$/, `${t("Avoir — commande")} $1`)
                      .replace(/^Achat (.+) — (.+)$/, `${t("Achat")} $1 — $2`)
                      .replace(/^Recharge de solde — Mobile Money(?: \((.+)\))?$/, (_m, p1) =>
                        p1 ? `${t("Recharge de solde — Mobile Money")} (${p1})` : t("Recharge de solde — Mobile Money"),
                      )}
                  </td>
                  <td className={`text-right font-semibold whitespace-nowrap ${entry.amountXof > 0 ? "text-success" : "text-ink"}`}>
                    {entry.amountXof > 0 ? "+" : "−"}
                    {formatXof(Math.abs(entry.amountXof))}
                  </td>
                  <td className="text-right whitespace-nowrap text-muted">{formatXof(entry.balanceAfterXof)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <EmptyState title={t("Aucun mouvement pour l'instant")} description={t("Les recharges, avoirs et achats payés avec le solde apparaîtront ici.")} />
      )}
    </div>
  );
}
