import type { Metadata } from "next";
import Link from "next/link";
import { ActionButton } from "@/components/forms";
import { Badge, EmptyState, PageHeader } from "@/components/ui";
import { costXof, coversCost, marginXof } from "@/lib/pricing";
import { formatUsdCents, formatXof } from "@/lib/format";
import { publishAllAction, syncCatalogAction, togglePublishAction } from "@/server/actions/admin";
import { adminListProducts } from "@/server/services/catalog";
import { getSettings } from "@/server/services/settings";
import { requireAdmin } from "@/server/auth/session";

export const metadata: Metadata = { title: "Produits" };

export default async function AdminProductsPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  // Le layout ne suffit pas (il ne se ré-exécute pas à chaque navigation) : chaque page vérifie le droit elle-même.
  await requireAdmin("/admin");
  const params = await searchParams;
  const q = (Array.isArray(params.q) ? (params.q[0] ?? "") : (params.q ?? "")).slice(0, 80);
  const [products, settings] = await Promise.all([adminListProducts(q), getSettings()]);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Produits"
        description="Le catalogue vient du fournisseur. Vous décidez de ce qui est publié et à quel prix."
        actions={
          <>
            <ActionButton action={syncCatalogAction} fields={{}} className="btn btn-soft btn-sm" busy="Synchronisation du catalogue…">
              Synchroniser
            </ActionButton>
            <ActionButton action={publishAllAction} fields={{}} className="btn btn-brand btn-sm" busy="Publication des produits…" confirm="Publier tous les produits vendables qui ne le sont pas encore ?">
              Publier tout le vendable
            </ActionButton>
          </>
        }
      />
      <form method="get" className="flex flex-wrap items-end gap-3">
        <div className="min-w-0 flex-1 basis-60">
          <label htmlFor="q" className="label">
            Rechercher
          </label>
          <input id="q" name="q" type="search" defaultValue={q} maxLength={80} placeholder="Nom, catégorie ou identifiant" className="input" />
        </div>
        <button type="submit" className="btn btn-soft">
          Rechercher
        </button>
      </form>

      {products.length ? (
        <div className="table-wrap">
          <table className="table">
            <caption className="sr-only">Produits</caption>
            <thead>
              <tr>
                <th scope="col">Produit</th>
                <th scope="col">Fournisseur</th>
                <th scope="col" className="text-right">
                  Coût
                </th>
                <th scope="col" className="text-right">
                  Coût d&apos;achat (FCFA)
                </th>
                <th scope="col" className="text-right">
                  Prix de vente
                </th>
                <th scope="col" className="text-right">
                  Bénéfice
                </th>
                <th scope="col">Boutique</th>
                <th scope="col">
                  <span className="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {products.map((p) => {
                // Rouge si le prix ne couvre pas le coût d'achat : la vente est alors bloquée.
                const profitable = coversCost(p.priceXof, p.costUsdCents, settings.usdRate);
                return (
                  <tr key={p.id}>
                    <td>
                      <Link href={`/admin/produits/${p.id}`} className="font-medium hover:underline">
                        {p.title}
                      </Link>
                      <span className="block text-xs text-muted">{p.shelf || "Sans catégorie"}</span>
                    </td>
                    <td>
                      {!p.supplierActive ? <Badge tone="danger">Retiré</Badge> : !p.supplierPurchasable ? <Badge tone="warning">Non vendable</Badge> : p.inStock ? <Badge tone="success">{p.stock === null ? "En stock" : `${p.stock} en stock`}</Badge> : <Badge tone="danger">Rupture</Badge>}
                    </td>
                    <td className="text-right whitespace-nowrap text-muted">{formatUsdCents(p.costUsdCents)}</td>
                    <td className="text-right whitespace-nowrap">{formatXof(costXof(p.costUsdCents, settings.usdRate))}</td>
                    <td className="text-right font-semibold whitespace-nowrap">
                      {formatXof(p.priceXof)}
                      {p.priceXofOverride ? <span className="block text-xs font-normal text-muted">prix fixe</span> : null}
                    </td>
                    <td className={`text-right whitespace-nowrap ${profitable ? "text-success" : "font-semibold text-danger"}`}>{formatXof(marginXof(p.priceXof, p.costUsdCents, settings.usdRate))}</td>
                    <td>{p.isPublished ? <Badge tone="success">Publié</Badge> : <Badge>Masqué</Badge>}</td>
                    <td className="text-right">
                      <ActionButton action={togglePublishAction} fields={{ id: p.id, publish: p.isPublished ? "0" : "1" }}>
                        {p.isPublished ? "Masquer" : "Publier"}
                      </ActionButton>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : (
        <EmptyState title={q ? "Aucun produit ne correspond" : "Catalogue vide"} description={q ? "Modifiez la recherche." : "Cliquez sur « Synchroniser » pour importer le catalogue du fournisseur."} />
      )}
    </div>
  );
}
