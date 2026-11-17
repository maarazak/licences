import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ActionForm, SubmitButton } from "@/components/forms";
import { Details } from "@/components/ui";
import { deliveryKindLabel } from "@/lib/enums";
import { formatDateTime, formatUsdCents, formatWarranty, formatXof } from "@/lib/format";
import { costXof, coversCost, marginXof } from "@/lib/pricing";
import { updateProductAction } from "@/server/actions/admin";
import { adminGetProduct } from "@/server/services/catalog";
import { getSettings } from "@/server/services/settings";
import { requireAdmin } from "@/server/auth/session";

export const metadata: Metadata = { title: "Produit" };

export default async function AdminProductPage({ params }: { params: Promise<{ id: string }> }) {
  // Le layout ne suffit pas (il ne se ré-exécute pas à chaque navigation) : chaque page vérifie le droit elle-même.
  await requireAdmin("/admin");
  const { id } = await params;
  const [product, settings] = await Promise.all([adminGetProduct(id), getSettings()]);
  if (!product) notFound();
  // Coût d'achat et bénéfice au taux appliqué (le plus élevé entre le taux minimum et le taux réel) ; la vente est bloquée sous ce coût.
  const profitable = coversCost(product.priceXof, product.costUsdCents, settings.usdRate);

  return (
    <div className="space-y-6">
      <nav aria-label="Fil d'Ariane" className="text-xs text-muted">
        <Link href="/admin/produits" className="hover:text-ink">
          Produits
        </Link>{" "}
        / <span className="text-ink">{product.name}</span>
      </nav>
      <h1 className="h1">{product.title}</h1>
      {!profitable ? <p className="alert alert-error">Le prix de vente ne couvre pas le coût : la vente de ce produit est bloquée. Corrigez le prix fixe, la marge ou le taux USD.</p> : null}

      <div className="grid gap-6 lg:grid-cols-[1fr_340px] lg:items-start">
        <ActionForm action={updateProductAction} className="card space-y-5 p-5">
          <input type="hidden" name="id" value={product.id} />
          <div className="flex flex-wrap gap-6">
            <label className="flex cursor-pointer items-center gap-2 text-sm font-medium">
              <input type="checkbox" name="isPublished" defaultChecked={product.isPublished} className="size-4 accent-brand" />
              Publié dans la boutique
            </label>
            <label className="flex cursor-pointer items-center gap-2 text-sm font-medium">
              <input type="checkbox" name="isFeatured" defaultChecked={product.isFeatured} className="size-4 accent-brand" />
              Mis en avant
            </label>
          </div>
          <div>
            <label htmlFor="displayName" className="label">
              Nom affiché
            </label>
            <input id="displayName" name="displayName" defaultValue={product.displayName ?? ""} maxLength={255} placeholder={product.name} className="input" />
            <p className="hint">Vide = nom du fournisseur.</p>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label htmlFor="shopCategory" className="label">
                Catégorie boutique
              </label>
              <input id="shopCategory" name="shopCategory" defaultValue={product.shopCategory ?? ""} maxLength={100} placeholder="IA et assistants, Création, Bureautique…" className="input" />
              <p className="hint">Sert au filtre de la boutique.</p>
            </div>
            <div>
              <label htmlFor="durationLabel" className="label">
                Durée affichée
              </label>
              <input id="durationLabel" name="durationLabel" defaultValue={product.durationLabel ?? ""} maxLength={60} placeholder={product.duration} className="input" />
              <p className="hint">Vide = durée annoncée par le fournisseur.</p>
            </div>
            <div>
              <label htmlFor="priceXofOverride" className="label">
                Prix fixe (FCFA)
              </label>
              <input id="priceXofOverride" name="priceXofOverride" inputMode="numeric" defaultValue={product.priceXofOverride ?? ""} className="input" />
              <p className="hint">Vide = prix calculé (coût × taux × marge).</p>
            </div>
            <div>
              <label htmlFor="marginPercentOverride" className="label">
                Marge propre (%)
              </label>
              <input id="marginPercentOverride" name="marginPercentOverride" inputMode="numeric" defaultValue={product.marginPercentOverride ?? ""} className="input" />
              <p className="hint">Vide = marge générale ({settings.marginPercent} %).</p>
            </div>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label htmlFor="featuredRank" className="label">
                Rang « produit phare »
              </label>
              <input id="featuredRank" name="featuredRank" inputMode="numeric" defaultValue={product.featuredRank ?? ""} className="input" />
              <p className="hint">1 = affiché en premier sur l&apos;accueil. Vide = pas un produit phare (cochez aussi « Mis en avant »).</p>
            </div>
          </div>
          <div>
            <label htmlFor="description" className="label">
              Description (français)
            </label>
            <textarea id="description" name="description" rows={4} maxLength={5000} defaultValue={product.description ?? ""} className="input" />
          </div>
          <div>
            <label htmlFor="descriptionEn" className="label">
              Description (anglais)
            </label>
            <textarea id="descriptionEn" name="descriptionEn" rows={4} maxLength={5000} defaultValue={product.descriptionEn ?? ""} className="input" />
            <p className="hint">Vide = la version française est affichée.</p>
          </div>
          <div>
            <label htmlFor="instructionsEn" className="label">
              Mode d&apos;emploi (anglais)
            </label>
            <textarea id="instructionsEn" name="instructionsEn" rows={4} maxLength={5000} defaultValue={product.instructionsEn ?? ""} className="input" />
          </div>
          <div>
            <label htmlFor="instructions" className="label">
              Mode d&apos;emploi (français)
            </label>
            <textarea id="instructions" name="instructions" rows={5} maxLength={5000} defaultValue={product.instructions ?? ""} className="input" />
            <p className="hint">Affiché sur la fiche et avec les accès livrés. Vide = le texte du fournisseur est montré à l&apos;acheteur avec ses accès.</p>
          </div>
          {product.supplierDescription ? (
            <details className="rounded-lg border border-line p-3">
              <summary className="cursor-pointer text-sm font-medium">Texte du fournisseur (non affiché sur la fiche)</summary>
              <p className="prose-plain mt-3 text-muted">{product.supplierDescription}</p>
            </details>
          ) : null}
          <SubmitButton className="btn btn-brand">Enregistrer</SubmitButton>
        </ActionForm>

        <aside className="card px-5 py-2">
          <Details
            rows={[
              ["Prix de vente", formatXof(product.priceXof)],
              ["Coût fournisseur", `${formatUsdCents(product.costUsdCents)} ≈ ${formatXof(costXof(product.costUsdCents, settings.usdRate))}`],
              ["Bénéfice unitaire", formatXof(marginXof(product.priceXof, product.costUsdCents, settings.usdRate))],
              ["Stock", product.stock === null ? (product.inStock ? "Disponible" : "Rupture") : String(product.stock)],
              ["Vendable", product.supplierPurchasable && product.supplierActive ? "Oui" : "Non"],
              ["Type d'accès", `${deliveryKindLabel(product.accessKind)} (${product.accessKind || "—"})`],
              ["E-mail requis", product.requiresEmail ? "Oui" : "Non"],
              ["Durée", product.duration],
              ["Garantie", formatWarranty(product.warranty)],
              ["Identifiant fournisseur", <span key="s" className="font-mono text-xs">{product.supplierId}</span>],
              ["Synchronisé le", formatDateTime(product.syncedAt)],
            ]}
          />
        </aside>
      </div>
    </div>
  );
}
