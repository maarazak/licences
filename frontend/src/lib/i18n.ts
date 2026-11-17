/**
 * Internationalisation : deux langues, français (par défaut) et anglais.
 * Les textes sont écrits en français dans le code et servent de clé : `t("Voir les offres")`.
 * En anglais, le dictionnaire (`i18n-en.ts`) donne la traduction ; un texte absent reste en français.
 * Variables : `t("{n} offres", { n: 3 })`. Les messages d'erreur renvoyés par le serveur (déjà complets)
 * sont traduits par des motifs (`EN_PATTERNS`).
 */
export const LANGS = ["fr", "en"] as const;
export type Lang = (typeof LANGS)[number];
export const LANG_LABELS: Record<Lang, string> = { fr: "Français", en: "English" };

export type Dict = Record<string, string>;
/** [expression régulière complète, remplacement avec $1, $2…] — sérialisable vers le navigateur. */
export type Pattern = readonly [source: string, replacement: string];
export type Vars = Record<string, string | number>;
export type TFn = (key: string, vars?: Vars) => string;

export function isLang(value: unknown): value is Lang {
  return value === "fr" || value === "en";
}

export function interpolate(text: string, vars?: Vars): string {
  if (!vars) return text;
  return text.replace(/\{(\w+)\}/g, (match, name: string) => (name in vars ? String(vars[name]) : match));
}

export function makeT(dict: Dict, patterns: readonly Pattern[] = []): TFn {
  const compiled = patterns.map(([source, replacement]) => [new RegExp(`^${source}$`), replacement] as const);
  return (key, vars) => {
    let out: string | undefined = Object.hasOwn(dict, key) ? dict[key] : undefined;
    if (out === undefined) {
      for (const [re, replacement] of compiled) {
        if (re.test(key)) {
          out = key.replace(re, replacement);
          break;
        }
      }
    }
    return interpolate(out ?? key, vars);
  };
}
