/** Calcul des prix de vente. Fonctions pures, testées dans `tests/pricing.test.ts`. */

export type PricingSettings = { usdRate: number; marginPercent: number; roundingStep: number; minMarginXof: number };

export type PricedProduct = {
  costUsdCents: number;
  priceXofOverride: number | null;
  marginPercentOverride: number | null;
};

/** Coût d'achat converti en FCFA (arrondi au franc supérieur). */
export function costXof(costUsdCents: number, usdRate: number): number {
  return Math.ceil((costUsdCents * usdRate) / 100);
}

/**
 * Prix de vente unitaire en FCFA :
 * - prix fixe du produit s'il est défini ;
 * - sinon le plus élevé de « coût × (1 + marge) » et « coût + marge minimale en FCFA », arrondi au multiple
 *   supérieur du pas. La marge minimale garantit un gain par vente même sur les produits très bon marché
 *   (un pourcentage seul ne donnerait que quelques dizaines de francs) ; elle ne s'applique pas quand le
 *   produit a sa propre marge, réglée volontairement.
 */
export function sellPriceXof(product: PricedProduct, s: PricingSettings): number {
  if (product.priceXofOverride && product.priceXofOverride > 0) return product.priceXofOverride;
  const margin = product.marginPercentOverride ?? s.marginPercent;
  // Arithmétique entière : cents × taux × (100 + marge) / 10 000.
  const byPercent = (product.costUsdCents * s.usdRate * (100 + margin)) / 10_000;
  const byMinimum = product.marginPercentOverride === null ? costXof(product.costUsdCents, s.usdRate) + Math.max(0, s.minMarginXof) : 0;
  const raw = Math.max(byPercent, byMinimum);
  const step = Math.max(1, s.roundingStep);
  return Math.max(step, Math.ceil(raw / step) * step);
}

/** Vrai si le prix de vente couvre le coût d'achat (pas de vente à perte). */
export function coversCost(priceXof: number, costUsdCents: number, usdRate: number): boolean {
  return priceXof >= costXof(costUsdCents, usdRate);
}

/** Marge unitaire en FCFA au taux donné. */
export function marginXof(priceXof: number, costUsdCents: number, usdRate: number): number {
  return priceXof - costXof(costUsdCents, usdRate);
}
