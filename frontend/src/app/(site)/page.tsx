import { BadgeCheck, ChevronDown, KeyRound, LifeBuoy, Package, RefreshCw, ShieldCheck, ShoppingBag, Smartphone, Zap } from "lucide-react";
import Link from "next/link";
import { BrandMarquee } from "@/components/brand-marquee";
import { CategoryBubble } from "@/components/category";
import { HeroCarousel, type HeroSlide } from "@/components/hero-carousel";
import { LoadMoreLink } from "@/components/load-more";
import { PayLogosFloating } from "@/components/payment-logos";
import { ProductCard } from "@/components/product-card";
import type { TFn } from "@/lib/i18n";
import { SITE_NAME } from "@/lib/site";
import { distinctBrands, diversify } from "@/server/brands";
import { getI18n } from "@/server/i18n";
import { getMoney } from "@/server/prefs";
import { listCatalog, listFeatured, type CatalogProduct } from "@/server/services/catalog";

function stockBadge(p: CatalogProduct, t: TFn): Pick<HeroSlide, "badge" | "badgeTone"> {
  if (!p.available) return { badge: t("Rupture"), badgeTone: "danger" };
  if (p.stock !== null && p.stock <= 3) return { badge: t("Plus que {n}", { n: p.stock }), badgeTone: "warning" };
  return { badge: t("Disponible"), badgeTone: "success" };
}

/** Produits phares affichés d'abord ; « Charger plus » en ajoute autant. */
const FEATURED_PAGE = 8;

export default async function HomePage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const params = await searchParams;
  const rawCount = Array.isArray(params.phares) ? params.phares[0] : params.phares;
  const featuredCount = Math.min(Math.max(Number.parseInt(rawCount ?? "", 10) || FEATURED_PAGE, FEATURED_PAGE), 200);
  const [{ t }, products, featuredRaw, { format }] = await Promise.all([getI18n(), listCatalog(), listFeatured(8), getMoney()]);
  const available = products.filter((p) => p.available);
  // Produits phares d'abord ; à défaut de sélection, un choix varié de marques.
  const featured = featuredRaw.length >= 4 ? featuredRaw : diversify(available, 8);
  // Section « Les plus demandés » : phares d'abord, puis les autres offres disponibles (déjà triées), sans doublon.
  const featuredIds = new Set(featured.map((p) => p.id));
  const pool = featured.concat(available.filter((p) => !featuredIds.has(p.id)));
  const brands = distinctBrands(featured.concat(available).map((p) => p.logo), 16);

  const slides: HeroSlide[] = featured.slice(0, 6).map((p) => ({
    slug: p.slug,
    title: p.title,
    duration: t(p.duration),
    price: format(p.priceXof),
    shelf: p.shelf ? t(p.shelf) : "",
    logo: p.logo,
    ...stockBadge(p, t),
  }));

  const categories = [...products.reduce((map, p) => (p.shelf ? map.set(p.shelf, (map.get(p.shelf) ?? 0) + 1) : map), new Map<string, number>())].sort((a, b) => b[1] - a[1]);

  const reasons = [
    { Icon: BadgeCheck, title: t("Prix final en FCFA"), text: t("Aucun frais ajouté au moment de payer."), gradient: "from-orange-400 to-accent" },
    { Icon: KeyRound, title: t("Accès dans votre espace"), text: t("Retrouvez-les à tout moment dans Mes achats."), gradient: "from-emerald-400 to-brand" },
    { Icon: LifeBuoy, title: t("Aide liée à votre commande"), text: t("Chaque demande est suivie jusqu'à sa résolution."), gradient: "from-sky-400 to-indigo-500" },
    { Icon: RefreshCw, title: t("Avoir ou remboursement"), text: t("Si la livraison échoue, vous choisissez."), gradient: "from-violet-400 to-fuchsia-500" },
  ];
  const steps = [
    { Icon: ShoppingBag, title: t("Choisissez votre offre"), text: t("Type d'accès, durée et garantie sont affichés avant de payer.") },
    { Icon: Smartphone, title: t("Payez en FCFA"), text: t("Mobile Money (Orange, Moov, MTN, Wave) en toute sécurité.") },
    { Icon: Package, title: t("Recevez votre accès"), text: t("Livraison automatique dans Mes achats, avec un e-mail de confirmation.") },
  ];
  const faq = [
    [t("Combien de temps pour recevoir mon accès ?"), t("En général quelques minutes après la confirmation du paiement. Certaines offres demandent une activation manuelle, indiquée sur la fiche.")],
    [t("Comment payer ?"), t("Par mobile money, sur la page sécurisée de notre prestataire de paiement. Le prix affiché est le prix final, en FCFA.")],
    [t("Et si mon accès ne fonctionne pas ?"), t("Signalez-le depuis votre commande pendant la durée de garantie : nous le remplaçons ou nous créditons votre solde.")],
    [t("Où retrouver mes accès ?"), t("Dans Mes achats, à tout moment, après connexion par code e-mail. Aucun mot de passe à retenir.")],
  ];
  const trust = [
    { Icon: Zap, text: t("Livraison en quelques minutes") },
    { Icon: ShieldCheck, text: t("Paiement mobile money sécurisé") },
    { Icon: RefreshCw, text: t("Remplacé ou crédité si l'accès échoue") },
  ];

  return (
    <>
      {/* ── Héros ── */}
      <section className="bg-hero relative overflow-hidden">
        <div aria-hidden="true" className="grid-lines absolute inset-0" />
        <div className="container-page relative grid gap-12 py-14 sm:py-18 lg:grid-cols-[1fr_1.05fr] lg:items-center lg:gap-10 lg:py-20">
          <div className="animate-fade-up">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-3">
              <p className="inline-flex items-center gap-2.5 rounded-full border border-line bg-white px-4 py-1.5 text-xs font-bold text-ink/80 shadow-sm">
                <span className="pulse-dot size-2 rounded-full bg-brand-bright" />
                {t("Livraison en quelques minutes")}
              </p>
              <PayLogosFloating />
            </div>
            <h1 className="mt-6 font-display text-[2.6rem] leading-[1.04] font-extrabold tracking-tight text-ink sm:text-6xl lg:text-[4rem]">
              {t("Vos abonnements premium,")} <span className="text-gradient">{t("payés en FCFA.")}</span>
            </h1>
            <p className="mt-6 max-w-lg text-lg leading-relaxed text-muted">{t("Netflix, ChatGPT, Claude, Canva et bien plus. Payez par mobile money, recevez votre accès en quelques minutes.")}</p>
            <div className="mt-8 flex flex-wrap gap-3">
              <Link href="#phares" className="btn btn-primary min-h-12 px-7 text-base">
                {t("Voir les produits phares")}
              </Link>
              <Link href="/boutique" className="btn btn-soft min-h-12 px-7 text-base">
                {t("Toute la boutique")}
              </Link>
            </div>
            <ul className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold text-ink/75">
              {trust.map(({ Icon, text }) => (
                <li key={text} className="flex items-center gap-2">
                  <Icon className="size-4 text-brand" aria-hidden="true" />
                  {text}
                </li>
              ))}
            </ul>
          </div>
          <div className="animate-fade-up delay-2">
            <HeroCarousel slides={slides} cta={t("Voir l'offre")} />
          </div>
        </div>
        <div className="relative border-y border-line bg-white/70">
          <BrandMarquee logos={brands} label={t("Marques proposées")} />
        </div>
      </section>

      {/* ── Produits phares ── */}
      <section id="phares" className="container-page scroll-mt-24 pt-16">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <p className="eyebrow text-accent-ink">{t("Les plus demandés")}</p>
            <h2 className="h2 mt-1">{t("Produits phares")}</h2>
          </div>
          <Link href="/boutique" className="link text-sm">
            {t("Toute la boutique")}
          </Link>
        </div>
        {featured.length ? (
          <>
            <div className="mt-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
              {pool.slice(0, featuredCount).map((product) => (
                <ProductCard key={product.id} product={product} priority={featuredIds.has(product.id)} />
              ))}
            </div>
            {pool.length > featuredCount ? <LoadMoreLink href={`/?phares=${featuredCount + FEATURED_PAGE}#phares`} label={t("Charger plus")} hint={t("{shown} sur {total} offres affichées", { shown: featuredCount, total: pool.length })} /> : null}
          </>
        ) : (
          <p className="card mt-7 px-6 py-12 text-center text-sm text-muted">{t("Les offres arrivent bientôt. Revenez dans un instant.")}</p>
        )}
      </section>

      {/* ── Catégories ── */}
      {categories.length ? (
        <section className="container-page pt-14">
          <h2 className="h3 text-lg">{t("Parcourir par catégorie")}</h2>
          <div className="mt-4 flex flex-wrap gap-2.5">
            {categories.slice(0, 8).map(([name, count]) => (
              <Link key={name} href={`/boutique?categorie=${encodeURIComponent(name)}`} className="chip">
                <CategoryBubble name={name} size="sm" />
                {t(name)}
                <span className="text-xs font-medium text-muted">{count}</span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}

      {/* ── Pourquoi nous ── */}
      <section className="container-page pt-16">
        <h2 className="h2">{t("Pourquoi acheter chez {name}", { name: SITE_NAME })}</h2>
        <div className="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {reasons.map(({ Icon, title, text, gradient }) => (
            <div key={title} className="card p-5">
              <span className={`flex size-11 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-[0_10px_20px_-10px_rgb(11_18_32/0.5)] ${gradient}`}>
                <Icon className="size-5" aria-hidden="true" />
              </span>
              <h3 className="h3 mt-4 text-[15px]">{title}</h3>
              <p className="mt-1.5 text-sm leading-relaxed text-muted">{text}</p>
            </div>
          ))}
        </div>
      </section>

      {/* ── Comment ça marche ── */}
      <section className="container-page pt-16">
        <h2 className="h2">{t("Comment ça marche")}</h2>
        <ol className="mt-7 grid gap-4 md:grid-cols-3">
          {steps.map(({ Icon, title, text }, i) => (
            <li key={title} className="card flex gap-4 p-5">
              <span className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-bright to-brand text-white shadow-[0_14px_28px_-10px_rgb(13_92_74/0.7)]">
                <Icon className="size-6" aria-hidden="true" />
              </span>
              <div>
                <p className="text-xs font-bold text-accent-ink">{t("Étape {n}", { n: i + 1 })}</p>
                <h3 className="h3 mt-0.5">{title}</h3>
                <p className="mt-1.5 text-sm leading-relaxed text-muted">{text}</p>
              </div>
            </li>
          ))}
        </ol>
      </section>

      {/* ── Questions fréquentes ── */}
      <section className="container-page max-w-3xl pt-16">
        <h2 className="h2">{t("Questions fréquentes")}</h2>
        <div className="mt-6 space-y-2.5">
          {faq.map(([question, answer]) => (
            <details key={question} className="card group px-5 py-4">
              <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-bold">
                {question}
                <ChevronDown className="size-4 shrink-0 text-muted transition-transform group-open:rotate-180" aria-hidden="true" />
              </summary>
              <p className="mt-2.5 text-sm leading-relaxed text-muted">{answer}</p>
            </details>
          ))}
        </div>
      </section>
    </>
  );
}
