/** Identité de la boutique (valeurs publiques). */
export const SITE_NAME = process.env.NEXT_PUBLIC_APP_NAME?.trim() || "Licences";

export const SITE_TAGLINE = "Abonnements et licences numériques, payés en FCFA";

export const NAV_LINKS = [
  { href: "/boutique", label: "Boutique" },
  { href: "/comment-ca-marche", label: "Comment ça marche" },
  { href: "/assistance", label: "Assistance" },
] as const;
