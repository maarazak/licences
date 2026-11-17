import type { Metadata } from "next";
import { ActionButton, ActionForm, SubmitButton } from "@/components/forms";
import { Badge, EmptyState, PageHeader } from "@/components/ui";
import { formatDate, formatXof } from "@/lib/format";
import { adjustWalletAction, setUserBlockedAction } from "@/server/actions/admin";
import { adminListCustomers } from "@/server/services/admin";
import { requireAdmin } from "@/server/auth/session";

export const metadata: Metadata = { title: "Clients" };

export default async function AdminCustomersPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  // Le layout ne suffit pas (il ne se ré-exécute pas à chaque navigation) : chaque page vérifie le droit elle-même.
  await requireAdmin("/admin");
  const params = await searchParams;
  const q = (Array.isArray(params.q) ? (params.q[0] ?? "") : (params.q ?? "")).slice(0, 80);
  const rows = await adminListCustomers(q);

  return (
    <div className="space-y-6">
      <PageHeader title="Clients" />
      <form method="get" className="flex flex-wrap items-end gap-3">
        <div className="min-w-0 flex-1 basis-60">
          <label htmlFor="q" className="label">
            Rechercher
          </label>
          <input id="q" name="q" type="search" defaultValue={q} maxLength={80} placeholder="E-mail, nom ou téléphone" className="input" />
        </div>
        <button type="submit" className="btn btn-soft">
          Rechercher
        </button>
      </form>
      {rows.length ? (
        <div className="table-wrap">
          <table className="table">
            <caption className="sr-only">Clients</caption>
            <thead>
              <tr>
                <th scope="col">Client</th>
                <th scope="col" className="text-right">
                  Commandes payées
                </th>
                <th scope="col" className="text-right">
                  Total livré
                </th>
                <th scope="col" className="text-right">
                  Solde
                </th>
                <th scope="col">Inscrit le</th>
                <th scope="col">Compte</th>
              </tr>
            </thead>
            <tbody>
              {rows.map(({ user, orders, spent }) => (
                <tr key={user.id}>
                  <td>
                    <span className="font-medium [overflow-wrap:anywhere]">{user.email}</span>
                    <span className="block text-xs text-muted">
                      {[user.firstName, user.lastName].filter(Boolean).join(" ") || "—"}
                      {user.phone ? ` · ${user.phone}` : ""}
                    </span>
                    {user.role === "admin" ? <Badge tone="info">Administrateur</Badge> : null}
                  </td>
                  <td className="text-right">{Number(orders)}</td>
                  <td className="text-right whitespace-nowrap">{formatXof(Number(spent))}</td>
                  <td className="text-right">
                    <span className="font-semibold whitespace-nowrap">{formatXof(user.balanceXof)}</span>
                    <details className="mt-1 text-left">
                      <summary className="cursor-pointer text-xs text-brand">Ajuster</summary>
                      <ActionForm action={adjustWalletAction} resetOnSuccess className="mt-2 w-56 space-y-2">
                        <input type="hidden" name="userId" value={user.id} />
                        <input name="amountXof" inputMode="numeric" required placeholder="Montant (+ ou −)" aria-label="Montant en FCFA, positif ou négatif" className="input" />
                        <input name="label" required minLength={3} maxLength={200} placeholder="Motif" aria-label="Motif de l'ajustement" className="input" />
                        <SubmitButton className="btn btn-soft btn-sm">Valider</SubmitButton>
                      </ActionForm>
                    </details>
                  </td>
                  <td className="whitespace-nowrap text-muted">{formatDate(user.createdAt)}</td>
                  <td>
                    {user.isBlocked ? <Badge tone="danger">Suspendu</Badge> : <Badge tone="success">Actif</Badge>}
                    {user.role !== "admin" ? (
                      <div className="mt-1.5">
                        <ActionButton action={setUserBlockedAction} fields={{ userId: user.id, blocked: user.isBlocked ? "0" : "1" }} confirm={user.isBlocked ? "Rétablir ce compte ?" : "Suspendre ce compte et le déconnecter ?"} danger={!user.isBlocked}>
                          {user.isBlocked ? "Rétablir" : "Suspendre"}
                        </ActionButton>
                      </div>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <EmptyState title="Aucun client" description={q ? "Aucun compte ne correspond à cette recherche." : "Les comptes apparaissent ici à la première connexion."} />
      )}
    </div>
  );
}
