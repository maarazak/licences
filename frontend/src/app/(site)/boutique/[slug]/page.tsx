import { ArrowRight, KeyRound, RefreshCw, ShieldCheck, Zap } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { BrandLogo, BrandTint } from "@/components/brand-logo";
import { ActionForm, SubmitButton } from "@/components/forms";
import { StockBadge } from "@/components/product-card";
import { Badge, Details } from "@/components/ui";
import { deliveryKindLabel } from "@/lib/enums";
import { formatWarranty, formatXof } from "@/lib/format";
import { getMoney } from "@/server/prefs";
import { productDescription } from "@/lib/product-text";
import { offerTerms } from "@/lib/terms";
import { addAlertAction } from "@/server/actions/shop";
import { currentUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";
import { hasActiveAlert } from "@/server/services/alerts";
import { getCatalogProduct, listRelated } from "@/server/services/catalog";

type Props = { params: Promise<{ slug: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const [product, { t, lang }] = await Promise.all([getCatalogProduct((await params).slug), getI18n()]);
  if (!product) return { title: t("Offre introuvable") };
  return { title: product.title, description: productDescription(product, lang).slice(0, 160) || undefined };
}

export default async function ProductPage({ params }: Props) {
  const { slug } = await params;
  const product = await getCatalogProduct(slug);
  if (!product) notFound();

  const [user, related, { t, lang }, { format, currency, rate }] = await Promise.all([currentUser(), listRelated(product), getI18n(), getMoney()]);
  const description = productDescription(product, lang);
  const guide = (lang === "en" ? product.instructionsEn?.trim() || product.instructions?.trim() : product.instructions?.trim()) ?? "";
  const terms = offerTerms({ title: product.title, accessKind: product.accessKind, duration: t(product.duration), warranty: product.warranty }, t);
  const alertActive = user && !product.available ? await hasActiveAlert(user.id, product.id) : false;
  const buyHref = `/commande/validation?produit=${encodeURIComponent(product.slug)}`;
  const access = t(deliveryKindLabel(product.accessKind));
  const duration = t(product.duration);

  const steps = [
    [t("Payez en FCFA"), t("Le prix affiché est le prix final.")],
    [t("Nous préparons votre accès"), t("Paiement et livraison sont confirmés séparément et suivis dans Mes achats.")],
    [t("Recevez vos instructions"), t("Lien, code ou compte selon l'offre, dans votre espace.")],
  ];
  const assurances = [
    { Icon: Zap, text: t("Livraison en quelques minutes") },
    { Icon: ShieldCheck, text: t("Paiement mobile money sécurisé") },
    { Icon: RefreshCw, text: t("Remplacé ou crédité si l'accès échoue") },
    { Icon: KeyRound, text: t("Vos accès restent dans Mes achats") },
  ];

  return (
    <div className="container-page py-8">
      <nav aria-label={t("Fil d'Ariane")} className="flex flex-wrap items-center gap-2 text-xs font-medium text-muted">
        <Link href="/boutique" className="hover:text-ink">
          {t("Boutique")}
        </Link>
        <span aria-hidden="true">/</span>
        {product.shelf ? (
          <>
            <Link href={`/boutique?categorie=${encodeURIComponent(product.shelf)}`} className="hover:text-ink">
              {t(product.shelf)}
            </Link>
            <span aria-hidden="true">/</span>
          </>
        ) : null}
        <span className="text-ink">{product.title}</span>
      </nav>

      <header className="card relative mt-5 overflow-hidden">
        <BrandTint logo={product.logo} strength={0.14} />
        <div aria-hidden="true" className="absolute inset-0 bg-gradient-to-r from-white/0 via-white/40 to-white" />
        <div className="relative flex flex-wrap items-center gap-6 p-6 sm:p-8">
          <BrandLogo logo={product.logo} size="xl" />
          <div className="min-w-0 flex-1">
            {product.shelf ? <p className="eyebrow text-accent-ink">{t(product.shelf)}</p> : null}
            <h1 translate="no" className="h1 mt-1 leading-tight">{product.title}</h1>
            <div className="mt-3 flex flex-wrap items-center gap-2">
              <Badge>{duration}</Badge>
              <Badge>{access}</Badge>
              <StockBadge product={product} />
            </div>
          </div>
        </div>
      </header>

      <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_360px] lg:items-start">
        <div className="min-w-0 space-y-9">
          {description ? <p className="text-lg leading-relaxed text-ink/85">{description}</p> : null}

          <section>
            <h2 className="h3 text-lg">{t("Ce que vous achetez")}</h2>
            <div className="card mt-3 px-5">
              <Details
                rows={[
                  [t("Type d'accès"), access],
                  [t("Durée"), duration],
                  [t("Garantie"), t(formatWarranty(product.warranty))],
                  ...(product.requiresEmail ? ([[t("Activation"), t("Sur l'adresse e-mail que vous indiquez à la commande")]] as Array<[string, string]>) : []),
                ]}
              />
            </div>
          </section>

          <section>
            <h2 className="h3 text-lg">{t("Comment ça se passe")}</h2>
            <ol className="mt-3 grid gap-3 sm:grid-cols-3">
              {steps.map(([title, text], index) => (
                <li key={title} className="card p-4">
                  <span className="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-brand-bright to-brand font-display text-sm font-extrabold text-white">{index + 1}</span>
                  <p className="mt-3 text-sm font-bold">{title}</p>
                  <p className="mt-1 text-xs leading-relaxed text-muted">{text}</p>
                </li>
              ))}
            </ol>
          </section>

          {guide ? (
            <section>
              <h2 className="h3 text-lg">{t("Mode d'emploi")}</h2>
              <div className="card mt-3 p-5">
                <p className="prose-plain">{guide}</p>
              </div>
            </section>
          ) : null}

          <details className="card group px-5 py-4">
            <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-bold">
              {t("Conditions de l'offre")}
              <span className="text-xs font-semibold text-brand group-open:hidden">{t("Voir toutes les conditions")}</span>
              <span className="hidden text-xs font-semibold text-muted group-open:inline">{t("Masquer")}</span>
            </summary>
            <ul className="mt-3 space-y-2 text-sm leading-relaxed text-muted">
              {terms.map((term) => (
                <li key={term}>{term}</li>
              ))}
            </ul>
          </details>

          {related.length ? (
            <section>
              <h2 className="h3 text-lg">{t("Autres formules {name}", { name: product.title.split(" ")[0] })}</h2>
              <ul className="mt-3 space-y-2.5">
                {related.map((item) => (
                  <li key={item.id}>
                    <Link href={`/boutique/${item.slug}`} className="group card flex flex-wrap items-center gap-3 p-3 pr-4 transition duration-300 hover:-translate-y-0.5 hover:shadow-pop">
                      <BrandLogo logo={item.logo} size="sm" />
                      <span translate="no" className="min-w-0 flex-1 text-sm font-bold">
                        {item.title}
                        <span className="block text-xs font-medium text-muted">
                          {t(item.duration)} · {t(deliveryKindLabel(item.accessKind))}
                        </span>
                      </span>
                      {!item.available ? <Badge tone="danger">{t("Rupture")}</Badge> : null}
                      <span className="font-display text-sm font-extrabold whitespace-nowrap">{format(item.priceXof)}</span>
                      <ArrowRight className="size-4 transition group-hover:translate-x-1" aria-hidden="true" />
                    </Link>
                  </li>
                ))}
              </ul>
            </section>
          ) : null}

          <p className="border-t border-line pt-5 text-xs leading-relaxed text-muted">
            {t("Avant d'acheter, consultez les")}{" "}
            <Link href="/conditions" className="link">
              {t("conditions de vente")}
            </Link>
            . {t("Boutique indépendante, non affiliée à l'éditeur du service.")}
          </p>
        </div>

        <aside className="card overflow-hidden lg:sticky lg:top-24">
          <div className="relative border-b border-line bg-gradient-to-br from-brand-soft via-white to-accent-soft px-6 py-6">
            <p className="text-xs font-bold tracking-[0.14em] text-muted uppercase">{t("Prix final")}</p>
            <p className="mt-1 font-display text-4xl font-extrabold tracking-tight text-ink">{format(product.priceXof)}</p>
            <p className="mt-1 text-xs text-muted">{t("Par unité · paiement en FCFA")}</p>
            {currency === "USD" && rate ? (
              <p className="mt-2 text-xs leading-relaxed text-muted">
                {t("Vous paierez {amount}", { amount: formatXof(product.priceXof) })} · {t("Taux du jour : 1 $ = {rate} FCFA", { rate: Math.round(rate) })}
              </p>
            ) : null}
          </div>
          <div className="space-y-5 p-6">
            <StockBadge product={product} />
            {product.available ? (
              <Link href={buyHref} className="btn btn-primary btn-block min-h-12 text-base">
                {t("Acheter maintenant")}
              </Link>
            ) : user ? (
              alertActive ? (
                <p className="alert alert-info">{t("Alerte activée : vous recevrez un e-mail au retour en stock.")}</p>
              ) : (
                <ActionForm action={addAlertAction} className="space-y-3">
                  <input type="hidden" name="productId" value={product.id} />
                  <SubmitButton className="btn btn-brand btn-block min-h-12">{t("Prévenez-moi du retour")}</SubmitButton>
                </ActionForm>
              )
            ) : (
              <Link href={`/connexion?next=${encodeURIComponent(`/boutique/${product.slug}`)}`} className="btn btn-brand btn-block min-h-12">
                {t("Prévenez-moi du retour")}
              </Link>
            )}
            <ul className="space-y-3 text-sm text-ink/80">
              {assurances.map(({ Icon, text }) => (
                <li key={text} className="flex items-center gap-3">
                  <Icon className="size-4 shrink-0 text-brand" aria-hidden="true" />
                  {text}
                </li>
              ))}
            </ul>
          </div>
        </aside>
      </div>
    </div>
  );
}
