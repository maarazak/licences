import type { Lang } from "./i18n";

/**
 * Retire du texte fournisseur ce qui n'a pas sa place sur le site : émojis, pictogrammes, traits décoratifs.
 * (Le texte brut du fournisseur est en anglais ; il n'est montré qu'en l'absence d'un mode d'emploi rédigé.)
 */
export function cleanSupplierText(text: string | null | undefined): string {
  if (!text) return "";
  return text
    .replace(/\r/g, "")
    .replace(/[─-╿•▪●]/g, " ")
    .replace(/[\p{Extended_Pictographic}️‍⃣]/gu, "")
    .replace(/[ \t]+/g, " ")
    .replace(/ *\n */g, "\n")
    .replace(/\n{2,}/g, "\n")
    .trim();
}

type Texts = { description: string | null; descriptionEn: string | null; instructions: string | null; instructionsEn: string | null; supplierDescription: string | null };

/** Description commerciale de l'offre dans la langue demandée (repli sur le français). */
export function productDescription(p: Pick<Texts, "description" | "descriptionEn">, lang: Lang): string {
  const fr = p.description?.trim() ?? "";
  return lang === "en" ? p.descriptionEn?.trim() || fr : fr;
}

/** Mode d'emploi dans la langue demandée ; à défaut, le texte du fournisseur nettoyé. */
export function productInstructions(p: Texts, lang: Lang): string {
  const own = lang === "en" ? p.instructionsEn?.trim() || p.instructions?.trim() : p.instructions?.trim();
  return own || p.instructions?.trim() || cleanSupplierText(p.supplierDescription);
}
