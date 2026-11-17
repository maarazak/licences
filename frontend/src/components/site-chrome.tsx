import { Globe, LifeBuoy, Package, ShoppingBag, UserRound } from "lucide-react";
import Link from "next/link";
import { LANGS, LANG_LABELS } from "@/lib/i18n";
import { NAV_LINKS, SITE_NAME } from "@/lib/site";
import { getI18n } from "@/server/i18n";
import { getMoney } from "@/server/prefs";
import { listSearchItems } from "@/server/services/catalog";
import { AccountMenu } from "./account-menu";
import { LogoLockup } from "./brand";
import { DesktopSearch, MobileSearch } from "./header-search";
import { PayLogosRow } from "./payment-logos";
import { CurrencyToggle, LanguageSelect } from "./preference-switches";

export function Logo({ href = "/" }: { href?: string }) {
  return (
    <Link href={href} className="inline-flex" aria-label={`${SITE_NAME}`}>
      <LogoLockup />
    </Link>
  );
}

/** Sélecteur de langue (français / anglais). */
export async function LanguageSwitch() {
  const { lang, t } = await getI18n();
  return (
    <div className="relative">
      <Globe className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
      <LanguageSelect
        lang={lang}
        options={LANGS.map((l) => ({ value: l, label: LANG_LABELS[l] }))}
        label={t("Langue")}
        className="min-h-9 cursor-pointer appearance-none rounded-full border border-line bg-white pr-3 pl-9 text-xs font-bold text-ink shadow-sm transition hover:border-ink/40"
      />
    </div>
  );
}

/** Sélecteur FCFA / dollar (conversion au taux du dollar du backoffice). Le paiement reste en FCFA. */
export async function CurrencySwitch() {
  const [{ currency }, { t }] = await Promise.all([getMoney(), getI18n()]);
  return (
    <CurrencyToggle
      currency={currency}
      label={t("Devise")}
      options={[
        { value: "XOF", label: "FCFA" },
        { value: "USD", label: "USD" },
      ]}
    />
  );
}

export async function SiteHeader({ signedIn, email, notice }: { signedIn: boolean; email?: string; notice: string }) {
  const [{ t }, items] = await Promise.all([getI18n(), listSearchItems()]);
  const searchItems = items.map((item) => ({ ...item, shelf: item.shelf ? t(item.shelf) : "" }));
  const searchLabels = {
    placeholder: t("Rechercher un produit…"),
    search: t("Rechercher un produit"),
    openList: t("Afficher la liste des produits"),
    close: t("Fermer"),
    none: t("Aucun produit ne correspond"),
    all: t("Tous les produits"),
    searchFor: t("Rechercher « {q} » dans la boutique", { q: "{q}" }),
  };
  const accountLabels = {
    menu: t("Menu du compte"),
    login: t("Se connecter"),
    dashboard: t("Tableau de bord"),
    orders: t("Mes achats"),
    balance: t("Mon solde"),
    support: t("Assistance"),
    profile: t("Profil"),
    logout: t("Se déconnecter"),
    preferences: t("Préférences"),
  };
  const preferences = (
    <>
      <CurrencySwitch />
      <LanguageSwitch />
    </>
  );
  return (
    <header className="sticky top-0 z-40 border-b border-line/70 bg-white/90 backdrop-blur-xl">
      {notice ? <p className="bg-brand px-4 py-2 text-center text-xs font-semibold text-white">{notice}</p> : null}
      <div className="container-page flex min-h-16 items-center gap-3 py-2 lg:gap-4">
        <Logo />
        <nav aria-label={t("Navigation principale")} className="hidden items-center gap-1 lg:flex">
          {NAV_LINKS.map((link) => (
            <Link key={link.href} href={link.href} className="rounded-full px-4 py-2 text-sm font-semibold text-ink/75 transition hover:bg-ink/5 hover:text-ink">
              {t(link.label)}
            </Link>
          ))}
        </nav>
        <div className="hidden min-w-0 max-w-md flex-1 lg:block">
          <DesktopSearch items={searchItems} labels={searchLabels} />
        </div>
        <div className="ml-auto flex items-center gap-2">
          <div className="hidden items-center gap-2 lg:flex">
            <CurrencySwitch />
            <LanguageSwitch />
          </div>
          <div className="lg:hidden">
            <MobileSearch items={searchItems} labels={searchLabels} />
          </div>
          {signedIn ? null : (
            <Link href="/connexion" className="btn btn-primary btn-sm hidden lg:inline-flex">
              <UserRound className="size-4" aria-hidden="true" />
              {t("Se connecter")}
            </Link>
          )}
          <AccountMenu signedIn={signedIn} email={email} labels={accountLabels} phoneOnly={!signedIn}>
            {preferences}
          </AccountMenu>
        </div>
      </div>
    </header>
  );
}

export async function SiteFooter() {
  const { t } = await getI18n();
  const columns = [
    {
      title: t("Boutique"),
      links: [
        { href: "/boutique", label: t("Toutes les offres") },
        { href: "/comment-ca-marche", label: t("Comment ça marche") },
        { href: "/assistance", label: t("Assistance") },
      ],
    },
    {
      title: t("Informations"),
      links: [
        { href: "/conditions", label: t("Conditions de vente") },
        { href: "/confidentialite", label: t("Confidentialité") },
        { href: "/mentions-legales", label: t("Mentions légales") },
      ],
    },
  ];
  return (
    <footer className="mt-20 border-t border-line bg-white pb-28 lg:pb-0">
      <div className="container-page grid gap-10 py-12 md:grid-cols-[1.6fr_repeat(2,1fr)]">
        <div className="max-w-xs">
          <Logo />
          <p className="mt-4 text-sm leading-relaxed text-muted">{t("Vos abonnements premium, payés en FCFA et livrés en quelques minutes.")}</p>
        </div>
        {columns.map((column) => (
          <nav key={column.title} aria-label={column.title}>
            <p className="text-xs font-bold tracking-[0.14em] text-ink uppercase">{column.title}</p>
            <ul className="mt-4 space-y-2.5">
              {column.links.map((link) => (
                <li key={link.href}>
                  <Link href={link.href} className="text-sm text-muted transition hover:text-ink">
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        ))}
      </div>
      <div className="border-t border-line">
        <div className="container-page flex flex-col gap-5 py-6 text-xs leading-relaxed text-muted sm:flex-row sm:items-center sm:justify-between">
          <p className="whitespace-nowrap">
            © {new Date().getFullYear()} {SITE_NAME}
          </p>
          <div className="flex flex-col gap-2.5 sm:items-end">
            <p className="text-[11px] font-bold tracking-[0.14em] text-ink uppercase">{t("Moyens de paiement")}</p>
            <PayLogosRow />
          </div>
        </div>
      </div>
    </footer>
  );
}

/** Barre de navigation flottante en bas d'écran sur mobile. */
export async function BottomNav() {
  const { t } = await getI18n();
  const links = [
    { href: "/boutique", label: t("Boutique"), Icon: ShoppingBag },
    { href: "/compte/achats", label: t("Mes achats"), Icon: Package },
    { href: "/assistance", label: t("Assistance"), Icon: LifeBuoy },
    { href: "/compte", label: t("Compte"), Icon: UserRound },
  ];
  return (
    <nav aria-label={t("Navigation mobile")} className="fixed inset-x-3 bottom-3 z-40 grid grid-cols-4 rounded-2xl border border-line bg-white/95 p-1 shadow-[0_12px_40px_-8px_rgb(11_18_32/0.25)] backdrop-blur-xl lg:hidden">
      {links.map(({ href, label, Icon }) => (
        <Link key={href} href={href} className="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[11px] font-bold text-ink transition active:bg-ink/5">
          <Icon className="size-5" aria-hidden="true" />
          {label}
        </Link>
      ))}
    </nav>
  );
}
