import type { Metadata } from "next";
import Link from "next/link";
import { SideNav } from "@/components/side-nav";
import { Logo } from "@/components/site-chrome";
import { logoutAction } from "@/server/actions/auth";
import { requireAdmin } from "@/server/auth/session";
import { dashboardStats } from "@/server/services/admin";

export const metadata: Metadata = { robots: { index: false, follow: false } };

/** Toutes les pages sous /admin passent par ce garde. Les actions revérifient le droit de leur côté. */
export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  const admin = await requireAdmin("/admin");
  const stats = await dashboardStats();
  const items = [
    { href: "/admin", label: "Tableau de bord", exact: true },
    { href: "/admin/commandes", label: "Commandes", badge: stats.counts.failed },
    { href: "/admin/produits", label: "Produits" },
    { href: "/admin/clients", label: "Clients" },
    { href: "/admin/assistance", label: "Assistance", badge: stats.openTickets },
    { href: "/admin/parametres", label: "Paramètres" },
    { href: "/admin/journal", label: "Journal" },
  ];
  return (
    <div className="min-h-dvh">
      <header className="sticky top-0 z-30 border-b border-line bg-paper">
        <div className="mx-auto flex h-14 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
          <div className="flex items-center gap-3">
            <Logo href="/admin" />
            <span className="badge badge-neutral">Backoffice</span>
          </div>
          <div className="flex items-center gap-2">
            <span className="hidden text-xs text-muted sm:inline">{admin.email}</span>
            <Link href="/" className="btn btn-soft btn-sm">
              Voir le site
            </Link>
            <form action={logoutAction}>
              <button type="submit" className="btn btn-soft btn-sm">
                Déconnexion
              </button>
            </form>
          </div>
        </div>
      </header>
      <div className="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[200px_1fr] lg:gap-8">
        <aside className="min-w-0 lg:sticky lg:top-20 lg:self-start">
          <SideNav items={items} label="Backoffice" />
        </aside>
        <main className="min-w-0">{children}</main>
      </div>
    </div>
  );
}
