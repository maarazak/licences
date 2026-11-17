import "server-only";
import { cache } from "react";
import { cookies } from "next/headers";
import { EN, EN_PATTERNS } from "@/lib/i18n-en";
import { isLang, makeT, type Lang, type TFn } from "@/lib/i18n";

/** Traducteur pour une langue donnée (pages, e-mails). */
export function translatorFor(lang: Lang): TFn {
  return lang === "en" ? makeT(EN, EN_PATTERNS) : makeT({});
}

/** Langue du visiteur : celle qu'il a choisie (cookie `lang`) ; le français par défaut, jamais déduit du navigateur. */
export const getLang = cache(async (): Promise<Lang> => {
  const stored = (await cookies()).get("lang")?.value;
  return isLang(stored) ? stored : "fr";
});

export const getI18n = cache(async (): Promise<{ lang: Lang; t: TFn }> => {
  const lang = await getLang();
  return { lang, t: translatorFor(lang) };
});
