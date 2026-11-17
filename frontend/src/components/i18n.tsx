"use client";

import { createContext, useContext, useMemo } from "react";
import { makeT, type Dict, type Lang, type Pattern, type TFn } from "@/lib/i18n";
import { formatMoney, type Currency } from "@/lib/money";

const Context = createContext<{ lang: Lang; t: TFn; currency: Currency; rate: number | null; money: (amountXof: number) => string }>({ lang: "fr", t: makeT({}), currency: "XOF", rate: null, money: (xof) => formatMoney(xof, "XOF", null, "fr") });

/** Fournit la langue et le dictionnaire aux composants client. En français, le dictionnaire est vide (le texte est sa propre clé). */
export function I18nProvider({ lang, dict, patterns, currency, rate, children }: { lang: Lang; dict: Dict; patterns: readonly Pattern[]; currency: Currency; rate: number | null; children: React.ReactNode }) {
  const value = useMemo(() => ({ lang, t: makeT(dict, patterns), currency, rate, money: (xof: number) => formatMoney(xof, currency, rate, lang) }), [lang, dict, patterns, currency, rate]);
  return <Context.Provider value={value}>{children}</Context.Provider>;
}

export function useI18n() {
  return useContext(Context);
}
