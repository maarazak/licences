import { ArrowRight, ShoppingCart } from "lucide-react";
import Link from "next/link";
import { deliveryKindLabel } from "@/lib/enums";
import { getI18n } from "@/server/i18n";
import { getMoney } from "@/server/prefs";
import type { CatalogProduct } from "@/server/services/catalog";
import { BrandLogo, BrandTint } from "./brand-logo";
import { Badge } from "./ui";

/** Pastille de disponibilité, identique sur la carte et sur la fiche. */
export async function StockBadge({ product }: { product: Pick<CatalogProduct, "available" | "stock" | "preorder"> }) {
  const { t } = await getI18n();
  if (!product.available) return <Badge tone="danger">{product.preorder ? t("Sur commande") : t("Rupture")}</Badge>;
  if (product.stock === null) return <Badge tone="success">{t("Disponible")}</Badge>;
  if (product.stock <= 3) return <Badge tone="warning">{t("Plus que {n}", { n: product.stock })}</Badge>;
  return <Badge tone="success">{t("{n} en stock", { n: product.stock })}</Badge>;
}

export async function ProductCard({ product, priority = false }: { product: CatalogProduct; priority?: boolean }) {
  const [{ t }, { format }] = await Promise.all([getI18n(), getMoney()]);
  return (
    // Carte cliquable (lien étiré sur le titre) + bouton « Acheter » distinct : pas de lien imbriqué dans un lien.
    <div className={`group relative flex flex-col overflow-hidden rounded-3xl border bg-white shadow-card transition duration-300 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/40 hover:-translate-y-1.5 hover:shadow-pop ${priority ? "border-brand-bright/40" : "border-line hover:border-brand-bright/40"}`}>
      <div className="relative h-28">
        <div className="absolute inset-0 overflow-hidden">
          <BrandTint logo={product.logo} strength={0.15} />
          <div aria-hidden="true" className="absolute inset-0 bg-gradient-to-b from-white/0 via-white/5 to-white" />
          <div aria-hidden="true" className="absolute -top-10 -right-6 size-32 rounded-full bg-white/60 blur-2xl" />
        </div>
        {product.shelf ? <span className="absolute top-3.5 left-4 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-bold text-ink/70 shadow-sm">{t(product.shelf)}</span> : null}
        <div className="absolute top-3 right-3.5">
          <StockBadge product={product} />
        </div>
        <BrandLogo logo={product.logo} size="lg" className="absolute -bottom-8 left-4 transition duration-300 group-hover:scale-105" />
      </div>
      <div className="flex flex-1 flex-col gap-3 px-4 pt-11 pb-4">
        <div>
          <h3 translate="no" className="h3 line-clamp-2 text-[15px] leading-snug">
            <Link href={`/boutique/${product.slug}`} className="outline-none after:absolute after:inset-0 after:z-0 after:content-['']">
              {product.title}
            </Link>
          </h3>
          <div className="mt-2 flex flex-wrap gap-1.5">
            <Badge>{t(product.duration)}</Badge>
            <Badge>{t(deliveryKindLabel(product.accessKind))}</Badge>
          </div>
        </div>
        <div className="mt-auto flex flex-wrap items-center justify-between gap-x-3 gap-y-2 pt-2">
          <p className="font-display text-xl font-extrabold tracking-tight whitespace-nowrap">{format(product.priceXof)}</p>
          {product.available ? (
            <Link href={`/commande/validation?produit=${encodeURIComponent(product.slug)}`} prefetch={false} aria-label={`${t("Acheter")} : ${product.title}`} className="btn btn-primary btn-sm relative z-10 shrink-0">
              <ShoppingCart className="size-4" aria-hidden="true" />
              {t("Acheter")}
            </Link>
          ) : (
            <span aria-hidden="true" className="flex size-9 items-center justify-center rounded-full bg-ink text-white transition duration-300 group-hover:bg-accent group-hover:shadow-glow">
              <ArrowRight className="size-4" />
            </span>
          )}
        </div>
      </div>
    </div>
  );
}
