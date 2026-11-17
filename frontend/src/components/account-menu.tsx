"use client";

import { LifeBuoy, LogOut, Package, UserRound, Wallet, LayoutDashboard } from "lucide-react";
import Link from "next/link";
import { useEffect, useId, useRef, useState } from "react";
import { logoutAction } from "@/server/actions/auth";

export type AccountLabels = {
  menu: string;
  login: string;
  dashboard: string;
  orders: string;
  balance: string;
  support: string;
  profile: string;
  logout: string;
  preferences: string;
};

/**
 * Menu du compte : une icône utilisateur qui ouvre la liste (tableau de bord, achats, solde, assistance, profil,
 * déconnexion). Sur téléphone, la langue et la devise y sont aussi (`children`). Visiteur : « Se connecter » + préférences.
 * `phoneOnly` : pour le visiteur non connecté, l'icône n'existe que sur téléphone (le bouton « Se connecter » est visible ailleurs).
 */
export function AccountMenu({ signedIn, email, labels, phoneOnly = false, children }: { signedIn: boolean; email?: string; labels: AccountLabels; phoneOnly?: boolean; children?: React.ReactNode }) {
  const [open, setOpen] = useState(false);
  const root = useRef<HTMLDivElement>(null);
  const button = useRef<HTMLButtonElement>(null);
  const panelId = useId();

  useEffect(() => {
    if (!open) return;
    const onPointer = (event: PointerEvent) => {
      if (root.current && !root.current.contains(event.target as Node)) setOpen(false);
    };
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpen(false);
        button.current?.focus();
      }
    };
    document.addEventListener("pointerdown", onPointer);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("pointerdown", onPointer);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  const close = () => setOpen(false);
  const links = [
    { href: "/compte", label: labels.dashboard, Icon: LayoutDashboard },
    { href: "/compte/achats", label: labels.orders, Icon: Package },
    { href: "/compte/solde", label: labels.balance, Icon: Wallet },
    { href: "/compte/assistance", label: labels.support, Icon: LifeBuoy },
    { href: "/compte/profil", label: labels.profile, Icon: UserRound },
  ];
  const row = "flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-sm font-semibold text-ink transition hover:bg-ink/5 active:bg-ink/5";

  return (
    <div ref={root} className={`relative ${phoneOnly ? "lg:hidden" : ""}`}>
      <button
        ref={button}
        type="button"
        aria-label={labels.menu}
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((v) => !v)}
        className={`flex size-11 items-center justify-center rounded-full shadow-sm transition active:scale-95 ${signedIn ? "bg-brand text-white hover:bg-brand-strong" : "border border-line bg-white text-ink"}`}
      >
        <UserRound className="size-5" aria-hidden="true" />
      </button>
      {open ? (
        <div id={panelId} className="absolute top-[calc(100%+0.5rem)] right-0 z-50 w-72 max-w-[calc(100vw-1.5rem)] rounded-2xl border border-line bg-white p-2 shadow-pop">
          {signedIn ? (
            <>
              {email ? (
                <p className="truncate px-3 pt-1.5 pb-2.5 text-xs font-semibold text-muted" title={email}>
                  {email}
                </p>
              ) : null}
              <nav aria-label={labels.menu}>
                {links.map(({ href, label, Icon }) => (
                  <Link key={href} href={href} onClick={close} className={row}>
                    <Icon className="size-4 text-muted" aria-hidden="true" />
                    {label}
                  </Link>
                ))}
              </nav>
            </>
          ) : (
            <Link href="/connexion" onClick={close} className="btn btn-primary btn-block min-h-11">
              <UserRound className="size-4" aria-hidden="true" />
              {labels.login}
            </Link>
          )}
          {children ? (
            <div className="mt-2 space-y-2 border-t border-line px-1 pt-3 pb-1 lg:hidden">
              <p className="px-2 text-[11px] font-bold tracking-[0.14em] text-muted uppercase">{labels.preferences}</p>
              <div className="flex flex-wrap items-center gap-2 px-2">{children}</div>
            </div>
          ) : null}
          {signedIn ? (
            <form action={logoutAction} className="mt-2 border-t border-line pt-2">
              <button type="submit" className={`${row} text-danger hover:bg-danger-soft`}>
                <LogOut className="size-4" aria-hidden="true" />
                {labels.logout}
              </button>
            </form>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
