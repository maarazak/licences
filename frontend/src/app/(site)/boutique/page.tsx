import { Search } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { CategoryBubble } from "@/components/category";
import { LoadMoreLink } from "@/components/load-more";
import { ProductCard } from "@/components/product-card";
import { getI18n } from "@/server/i18n";
import { listCatalog, listCategories, type CatalogFilters } from "@/server/services/catalog";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Boutique") };
}

type Search = Promise<Record<string, string | string[] | undefined>>;

const first = (value: string | string[] | undefined): string => (Array.isArray(value) ? (value[0] ?? "") : (value ?? ""));

/** Offres affichées par page ; « Charger plus » en ajoute autant. */
const PAGE_SIZE = 12;

const SORTS: Array<{ value: NonNullable<CatalogFilters["sort"]>; label: string }> = [
  { value: "featured", label: "Produits phares d'abord" },
  { value: "price_asc", label: "Prix croissant" },
  { value: "price_desc", label: "Prix décroissant" },
  { value: "name", label: "Nom (A à Z)" },
];

export default async function ShopPage({ searchParams }: { searchParams: Search }) {
  const { t } = await getI18n();
  const params = await searchParams;
  const q = first(params.q).slice(0, 80);
  const categories = await listCategories();
  const category = categories.includes(first(params.categorie)) ? first(params.categorie) : "";
  const availability = first(params.dispo) === "disponible" ? "available" : "all";
  const sort = SORTS.find((s) => s.value === first(params.tri))?.value ?? "featured";

  const shown = Math.min(Math.max(Number.parseInt(first(params.n), 10) || PAGE_SIZE, PAGE_SIZE), 500);
  const products = await listCatalog({ q, category, availability, sort });
  const visibleProducts = products.slice(0, shown);
  const filtered = !!(q || category || availability === "available");

  /** Lien de filtre qui conserve les autres critères. */
  const link = (patch: Record<string, string>) => {
    const current: Record<string, string> = { q, categorie: category, dispo: availability === "available" ? "disponible" : "", tri: sort === "featured" ? "" : sort, n: "", ...patch };
    const query = new URLSearchParams(Object.entries(current).filter(([, v]) => v));
    return `/boutique${query.size ? `?${query}` : ""}`;
  };

  return (
    <>
      <section className="bg-hero relative overflow-hidden">
        <div aria-hidden="true" className="grid-lines absolute inset-0" />
        <div className="container-page relative py-10 sm:py-12">
          <h1 className="font-display text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">{t("Boutique")}</h1>
          <p className="mt-2 max-w-xl text-muted">{t("Abonnements premium payés en FCFA, livrés dans votre espace.")}</p>

          <form method="get" className="mt-7 flex flex-col gap-3 rounded-2xl border border-line bg-white p-2.5 shadow-card sm:flex-row sm:items-center">
            {category ? <input type="hidden" name="categorie" value={category} /> : null}
            <div className="relative min-w-0 flex-1">
              <Search className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted" aria-hidden="true" />
              <label htmlFor="q" className="sr-only">
                {t("Rechercher un outil")}
              </label>
              <input id="q" name="q" type="search" defaultValue={q} maxLength={80} placeholder={t("Rechercher : Netflix, ChatGPT, Canva…")} className="input border-transparent bg-canvas pl-12 shadow-none" />
            </div>
            <label htmlFor="dispo" className="sr-only">
              {t("Disponibilité")}
            </label>
            <select id="dispo" name="dispo" defaultValue={availability === "available" ? "disponible" : ""} className="input sm:w-44">
              <option value="">{t("Toutes les offres")}</option>
              <option value="disponible">{t("En stock uniquement")}</option>
            </select>
            <label htmlFor="tri" className="sr-only">
              {t("Trier par")}
            </label>
            <select id="tri" name="tri" defaultValue={sort} className="input sm:w-52">
              {SORTS.map((s) => (
                <option key={s.value} value={s.value}>
                  {t(s.label)}
                </option>
              ))}
            </select>
            <button type="submit" className="btn btn-primary min-h-11 px-7">
              {t("Rechercher")}
            </button>
          </form>
        </div>
      </section>

      <div className="container-page py-8">
        {categories.length ? (
          <div className="-mx-4 flex gap-2.5 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label={t("Catégories")}>
            <Link href={link({ categorie: "" })} aria-current={!category ? "page" : undefined} className={`chip shrink-0 ${!category ? "chip-active" : ""}`}>
              {t("Tout")}
            </Link>
            {categories.map((name) => (
              <Link key={name} href={link({ categorie: name })} aria-current={category === name ? "page" : undefined} className={`chip shrink-0 ${category === name ? "chip-active" : ""}`}>
                <CategoryBubble name={name} size="sm" />
                {t(name)}
              </Link>
            ))}
          </div>
        ) : null}

        <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
          <p className="text-sm font-semibold text-muted">
            {products.length === 1 ? t("1 offre") : t("{n} offres", { n: products.length })}
            {category ? ` · ${t(category)}` : ""}
          </p>
          {filtered ? (
            <Link href="/boutique" className="link text-sm">
              {t("Effacer les filtres")}
            </Link>
          ) : null}
        </div>

        {products.length ? (
          <>
          <div className="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {visibleProducts.map((product) => (
              <ProductCard key={product.id} product={product} priority={product.isFeatured && sort === "featured" && !filtered} />
            ))}
          </div>
          {products.length > shown ? <LoadMoreLink href={link({ n: String(shown + PAGE_SIZE) })} label={t("Charger plus")} hint={t("{shown} sur {total} offres affichées", { shown, total: products.length })} /> : null}
          </>
        ) : (
          <div className="card mt-5 px-6 py-14 text-center">
            <p className="h3 text-lg">{filtered ? t("Aucune offre ne correspond à votre recherche") : t("Les offres arrivent bientôt. Revenez dans un instant.")}</p>
            {filtered ? (
              <Link href="/boutique" className="btn btn-brand mt-5">
                {t("Voir toutes les offres")}
              </Link>
            ) : null}
          </div>
        )}
      </div>
    </>
  );
}
