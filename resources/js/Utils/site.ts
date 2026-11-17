/** Identité de la boutique (valeurs publiques). */
export const SITE_NAME = (import.meta.env.VITE_APP_NAME as string | undefined)?.trim() || "Licences";

export const SITE_TAGLINE = "Abonnements et licences numériques, payés en FCFA";

export const NAV_LINKS = [
  { href: "/boutique", label: "Boutique" },
  { href: "/comment-ca-marche", label: "Comment ça marche" },
  { href: "/assistance", label: "Assistance" },
] as const;
