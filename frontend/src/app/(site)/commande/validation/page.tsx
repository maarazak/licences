import { randomUUID } from "node:crypto";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { CheckoutForm } from "@/components/checkout-form";
import { COUNTRIES, deliveryKindLabel } from "@/lib/enums";
import { offerTerms } from "@/lib/terms";
import { requireUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";
import { getCatalogProduct } from "@/server/services/catalog";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Valider votre commande"), robots: { index: false } };
}

export default async function CheckoutPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const params = await searchParams;
  const slug = Array.isArray(params.produit) ? (params.produit[0] ?? "") : (params.produit ?? "");
  const user = await requireUser(`/commande/validation?produit=${encodeURIComponent(slug)}`);
  const [product, { t }] = await Promise.all([getCatalogProduct(slug), getI18n()]);
  if (!product) notFound();

  if (!product.available) {
    return (
      <div className="container-page py-12">
        <div className="card mx-auto max-w-lg px-6 py-10 text-center">
          <h1 className="h2">{t("Cette offre n'est plus disponible")}</h1>
          <p className="mt-2 text-sm text-muted">{t("Elle est peut-être en rupture de stock. Vous pouvez demander à être prévenu de son retour.")}</p>
          <Link href={`/boutique/${product.slug}`} className="btn btn-soft mt-5">
            {t("Retour à l'offre")}
          </Link>
        </div>
      </div>
    );
  }

  const access = t(deliveryKindLabel(product.accessKind));
  const duration = t(product.duration);
  const terms = offerTerms({ title: product.title, accessKind: product.accessKind, duration, warranty: product.warranty }, t);

  return (
    <div className="container-page py-10">
      <h1 className="h1 mb-8">{t("Valider votre commande")}</h1>
      <CheckoutForm
        clientToken={randomUUID()}
        product={{ id: product.id, title: product.title, unitPriceXof: product.priceXof, maxQuantity: product.stock ?? 50, requiresEmail: product.requiresEmail, accessLabel: access, durationLabel: duration, logo: product.logo }}
        defaults={{
          fullName: [user.firstName, user.lastName].filter(Boolean).join(" "),
          firstName: user.firstName ?? "",
          lastName: user.lastName ?? "",
          phone: user.phone ?? "",
          country: COUNTRIES.some((c) => c.code === user.country) ? (user.country as string) : "",
          email: user.email,
        }}
        balanceXof={user.balanceXof}
        terms={terms}
      />
    </div>
  );
}
