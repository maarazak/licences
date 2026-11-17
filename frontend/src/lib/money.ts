import type { Lang } from "./i18n";

/** Devises d'affichage : le paiement se fait toujours en FCFA (XOF), le dollar n'est qu'une conversion. */
export const CURRENCIES = ["XOF", "USD"] as const;
export type Currency = (typeof CURRENCIES)[number];

export function isCurrency(value: unknown): value is Currency {
  return value === "XOF" || value === "USD";
}

const xofFormat = new Intl.NumberFormat("fr-FR", { maximumFractionDigits: 0 });

/** Montant à afficher dans la devise choisie. `rate` = FCFA pour 1 dollar (requis pour USD). */
export function formatMoney(amountXof: number, currency: Currency, rate: number | null, lang: Lang): string {
  if (currency === "USD" && rate) {
    return new Intl.NumberFormat(lang === "en" ? "en-US" : "fr-FR", { style: "currency", currency: "USD", currencyDisplay: "narrowSymbol", minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amountXof / rate);
  }
  return `${xofFormat.format(amountXof)} FCFA`;
}
