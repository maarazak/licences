import { describe, expect, it } from "vitest";
import { costXof, coversCost, marginXof, sellPriceXof } from "../src/lib/pricing";

const settings = { usdRate: 620, marginPercent: 25, roundingStep: 100, minMarginXof: 0 };
const base = { priceXofOverride: null, marginPercentOverride: null };

describe("costXof", () => {
  it("convertit des cents USD en FCFA, arrondi au franc supérieur", () => {
    expect(costXof(300, 620)).toBe(1860);
    expect(costXof(333, 620)).toBe(2065); // 2064,6 → 2065
    expect(costXof(1, 620)).toBe(7); // 6,2 → 7
  });
});

describe("sellPriceXof", () => {
  it("applique taux, marge et arrondi au pas supérieur", () => {
    // 3,00 $ × 620 × 1,25 = 2325 → 2400
    expect(sellPriceXof({ ...base, costUsdCents: 300 }, settings)).toBe(2400);
  });
  it("ne descend pas quand le prix tombe pile sur le pas", () => {
    // 4,00 $ × 500 × 1,00 = 2000
    expect(sellPriceXof({ ...base, costUsdCents: 400 }, { usdRate: 500, marginPercent: 0, roundingStep: 100, minMarginXof: 0 })).toBe(2000);
  });
  it("donne la priorité au prix fixe", () => {
    expect(sellPriceXof({ costUsdCents: 300, priceXofOverride: 5000, marginPercentOverride: 80 }, settings)).toBe(5000);
  });
  it("utilise la marge propre au produit", () => {
    // 3,00 $ × 620 × 1,5 = 2790 → 2800
    expect(sellPriceXof({ ...base, costUsdCents: 300, marginPercentOverride: 50 }, settings)).toBe(2800);
  });
  it("ignore un prix fixe nul", () => {
    expect(sellPriceXof({ costUsdCents: 300, priceXofOverride: 0, marginPercentOverride: null }, settings)).toBe(2400);
  });
  it("ne renvoie jamais un prix inférieur au pas", () => {
    expect(sellPriceXof({ ...base, costUsdCents: 1 }, settings)).toBe(100);
  });
  it("le prix calculé couvre toujours le coût, quelle que soit la marge", () => {
    for (const cents of [1, 99, 150, 333, 1999, 12345]) {
      for (const marginPercent of [0, 5, 25, 300]) {
        const s = { usdRate: 617, marginPercent, roundingStep: 50, minMarginXof: 0 };
        expect(coversCost(sellPriceXof({ ...base, costUsdCents: cents }, s), cents, s.usdRate)).toBe(true);
      }
    }
  });
});

describe("marge minimale en FCFA (comme getpass.shop)", () => {
  const gp = { usdRate: 620, marginPercent: 40, roundingStep: 250, minMarginXof: 1000 };
  it("un produit très bon marché garde au moins la marge minimale", () => {
    // 0,89 $ → coût 552 FCFA ; 40 % ne donnerait que 773 → on prend 552 + 1 000 = 1 552 → 1 750
    expect(sellPriceXof({ ...base, costUsdCents: 89 }, gp)).toBe(1750);
  });
  it("un produit plus cher suit le pourcentage quand il dépasse le minimum", () => {
    // 5,49 $ → coût 3 404 ; 40 % = 4 766 > 3 404 + 1 000 → 5 000
    expect(sellPriceXof({ ...base, costUsdCents: 549 }, gp)).toBe(5000);
  });
  it("la marge propre d'un produit est respectée telle quelle", () => {
    expect(sellPriceXof({ ...base, costUsdCents: 89, marginPercentOverride: 40 }, gp)).toBe(1000);
  });
  it("le prix fixe reste prioritaire", () => {
    expect(sellPriceXof({ costUsdCents: 89, priceXofOverride: 3000, marginPercentOverride: null }, gp)).toBe(3000);
  });
  it("une marge minimale à 0 retrouve le calcul au pourcentage seul", () => {
    expect(sellPriceXof({ ...base, costUsdCents: 300 }, { ...settings })).toBe(2400);
  });
  it("le prix couvre toujours coût + marge minimale", () => {
    for (const cents of [3, 39, 89, 199, 549, 1999]) {
      const price = sellPriceXof({ ...base, costUsdCents: cents }, gp);
      expect(price - costXof(cents, gp.usdRate)).toBeGreaterThanOrEqual(1000);
      expect(price % 250).toBe(0);
    }
  });
});

describe("coversCost / marginXof", () => {
  it("détecte une vente à perte", () => {
    expect(coversCost(1800, 300, 620)).toBe(false);
    expect(coversCost(1860, 300, 620)).toBe(true);
    expect(marginXof(2400, 300, 620)).toBe(540);
    expect(marginXof(1800, 300, 620)).toBe(-60);
  });
});
