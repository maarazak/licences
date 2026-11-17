"use client";

import { Bell, KeyRound, LayoutDashboard, LifeBuoy, Package, Settings, UserRound, Wallet, type LucideIcon } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";

const ICONS: Record<string, LucideIcon> = { dashboard: LayoutDashboard, orders: Package, access: KeyRound, wallet: Wallet, alerts: Bell, support: LifeBuoy, settings: Settings, profile: UserRound };

export type NavItem = { href: string; label: string; badge?: number; exact?: boolean; icon?: keyof typeof ICONS };

/** Menu latéral (espace client et backoffice) : vertical sur ordinateur, défilant sur mobile. */
export function SideNav({ items, label }: { items: NavItem[]; label: string }) {
  const pathname = usePathname();
  return (
    <nav aria-label={label} className="-mx-4 flex gap-1 overflow-x-auto px-4 pb-1 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0">
      {items.map((item) => {
        const active = item.exact ? pathname === item.href : pathname === item.href || pathname.startsWith(`${item.href}/`);
        const Icon = item.icon ? ICONS[item.icon] : null;
        return (
          <Link
            key={item.href}
            href={item.href}
            aria-current={active ? "page" : undefined}
            className={`flex min-h-10 shrink-0 items-center justify-between gap-3 rounded-xl px-3 text-sm font-semibold whitespace-nowrap transition ${active ? "bg-brand text-white shadow-[0_10px_20px_-12px_rgb(13_92_74/0.9)]" : "text-ink/75 hover:bg-ink/5 hover:text-ink"}`}
          >
            <span className="flex items-center gap-2.5">
              {Icon ? <Icon className="size-4" aria-hidden="true" /> : null}
              {item.label}
            </span>
            {item.badge ? <span className="rounded-full bg-accent px-1.5 text-[11px] font-bold text-white">{item.badge}</span> : null}
          </Link>
        );
      })}
    </nav>
  );
}
