import type { Metadata } from "next";
import { EmptyState, PageHeader } from "@/components/ui";
import { formatDateTime } from "@/lib/format";
import { adminListAudit } from "@/server/services/admin";
import { requireAdmin } from "@/server/auth/session";

export const metadata: Metadata = { title: "Journal" };

export default async function AdminAuditPage() {
  // Le layout ne suffit pas (il ne se ré-exécute pas à chaque navigation) : chaque page vérifie le droit elle-même.
  await requireAdmin("/admin");
  const logs = await adminListAudit();
  return (
    <div className="space-y-6">
      <PageHeader title="Journal" description="Les 300 dernières actions effectuées dans le backoffice." />
      {logs.length ? (
        <div className="table-wrap">
          <table className="table">
            <caption className="sr-only">Journal des actions</caption>
            <thead>
              <tr>
                <th scope="col">Date</th>
                <th scope="col">Administrateur</th>
                <th scope="col">Action</th>
                <th scope="col">Détails</th>
                <th scope="col">Adresse IP</th>
              </tr>
            </thead>
            <tbody>
              {logs.map((log) => (
                <tr key={log.id}>
                  <td className="whitespace-nowrap text-muted">{formatDateTime(log.createdAt)}</td>
                  <td className="max-w-48 truncate">{log.adminEmail}</td>
                  <td className="font-mono text-xs">{log.action}</td>
                  <td className="text-muted [overflow-wrap:anywhere]">{[log.target, log.details].filter(Boolean).join(" — ") || "—"}</td>
                  <td className="font-mono text-xs text-muted">{log.ip ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <EmptyState title="Journal vide" description="Les actions du backoffice apparaîtront ici." />
      )}
    </div>
  );
}
