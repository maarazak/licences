import { LogOut } from "lucide-react";
import { logoutAction } from "@/server/actions/auth";
import { SideNav } from "@/components/side-nav";
import { currentUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";

export default async function AccountLayout({ children }: { children: React.ReactNode }) {
  // Sans session, chaque page redirige elle-même vers la connexion avec son adresse exacte (lien profond conservé).
  const [user, { t }] = await Promise.all([currentUser(), getI18n()]);
  if (!user) return <>{children}</>;
  const items = [
    { href: "/compte", label: t("Tableau de bord"), exact: true, icon: "dashboard" },
    { href: "/compte/achats", label: t("Mes achats"), icon: "orders" },
    { href: "/compte/acces", label: t("Mes accès"), icon: "access" },
    { href: "/compte/solde", label: t("Mon solde"), icon: "wallet" },
    { href: "/compte/alertes", label: t("Mes alertes"), icon: "alerts" },
    { href: "/compte/assistance", label: t("Assistance"), icon: "support" },
    { href: "/compte/profil", label: t("Profil"), icon: "profile" },
  ] as const;
  const name = [user.firstName, user.lastName].filter(Boolean).join(" ");
  return (
    <div className="container-page grid gap-6 py-8 lg:grid-cols-[230px_1fr] lg:gap-10">
      <aside className="min-w-0 lg:sticky lg:top-24 lg:self-start">
        <div className="mb-4 hidden items-center gap-3 lg:flex">
          <span aria-hidden="true" className="flex size-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-bright to-brand font-display text-sm font-bold text-white">
            {(name || user.email).charAt(0).toUpperCase()}
          </span>
          <div className="min-w-0">
            {name ? <p className="truncate text-sm font-bold">{name}</p> : null}
            <p className="truncate text-xs text-muted">{user.email}</p>
          </div>
        </div>
        <SideNav items={[...items]} label={t("Espace client")} />
        <form action={logoutAction} className="mt-2 lg:mt-4">
          <button type="submit" className="btn btn-soft btn-sm w-full justify-start">
            <LogOut className="size-4" aria-hidden="true" />
            {t("Se déconnecter")}
          </button>
        </form>
      </aside>
      <div className="min-w-0">{children}</div>
    </div>
  );
}
