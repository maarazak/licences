import "server-only";
import { cache } from "react";
import { cookies } from "next/headers";
import type { Lang } from "@/lib/i18n";
import { formatMoney, isCurrency, type Currency } from "@/lib/money";
import { getI18n } from "./i18n";
import { getSettings } from "./services/settings";

export type MoneyContext = {
  /** Devise d'affichage choisie par le visiteur. */
  currency: Currency;
  /** FCFA pour 1 dollar : notre taux (réglage « Taux du dollar » du backoffice). */
  rate: number;
  lang: Lang;
  /** Formate un montant en FCFA dans la devise choisie. */
  format: (amountXof: number) => string;
};

/** Devise d'affichage choisie par le visiteur (FCFA par défaut) et formateur de montants. */
export const getMoney = cache(async (): Promise<MoneyContext> => {
  const [{ usdRate: rate }, { lang }, stored] = await Promise.all([getSettings(), getI18n(), cookies().then((c) => c.get("devise")?.value)]);
  const currency: Currency = isCurrency(stored) ? stored : "XOF";
  return { currency, rate, lang, format: (amountXof) => formatMoney(amountXof, currency, rate, lang) };
});
