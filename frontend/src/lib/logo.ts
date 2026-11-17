/** Logo d'une offre, décrit par des données simples (sérialisable vers les composants client). */
export type LogoSpec =
  /** Pictogramme vectoriel de la marque, sur tuile blanche. */
  | { kind: "icon"; path: string; hex: string; label: string; tint: string; known: boolean }
  /** Fichier de logo fourni dans `public/brands/`. */
  | { kind: "image"; src: string; label: string; tint: string; known: boolean }
  /** Monogramme aux couleurs de la marque (quand aucun logo n'est disponible). */
  | { kind: "mono"; letters: string; hex: string; label: string; tint: string; known: boolean };
