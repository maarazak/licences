import type { LogoSpec } from "@/lib/logo";

const SIZES = { sm: "size-10 rounded-xl", md: "size-14 rounded-2xl", lg: "size-[72px] rounded-2xl", xl: "size-24 rounded-3xl" } as const;
export type LogoSize = keyof typeof SIZES;

/**
 * Tuile de logo : pictogramme de la marque sur fond blanc, image fournie, ou monogramme coloré.
 * Aucun style en ligne (la CSP de production les interdit) : les couleurs passent par des attributs SVG.
 */
export function BrandLogo({ logo, size = "md", className = "" }: { logo: LogoSpec; size?: LogoSize; className?: string }) {
  const base = `${SIZES[size]} flex shrink-0 items-center justify-center overflow-hidden shadow-logo ring-1 ring-black/5 ${className}`;

  if (logo.kind === "mono") {
    return (
      <span className={base} role="img" aria-label={logo.label}>
        <svg viewBox="0 0 64 64" className="size-full" aria-hidden="true">
          <rect width="64" height="64" fill={logo.hex} />
          <circle cx="54" cy="8" r="34" fill="#fff" fillOpacity="0.14" />
          <rect y="38" width="64" height="26" fill="#000" fillOpacity="0.12" />
          <text x="32" y="43" textAnchor="middle" fontSize={logo.letters.length > 1 ? 24 : 30} fontWeight="800" fill="#fff" className="font-display">
            {logo.letters}
          </text>
        </svg>
      </span>
    );
  }
  if (logo.kind === "image") {
    return (
      <span className={`${base} bg-white`} role="img" aria-label={logo.label}>
        {/* eslint-disable-next-line @next/next/no-img-element -- petit logo local déposé dans public/brands */}
        <img src={logo.src} alt="" loading="lazy" className="size-full object-contain p-[18%]" />
      </span>
    );
  }
  return (
    <span className={`${base} bg-white`} role="img" aria-label={logo.label}>
      <svg viewBox="0 0 24 24" className="size-[52%]" aria-hidden="true">
        <path d={logo.path} fill={logo.hex} />
      </svg>
    </span>
  );
}

/** Voile coloré aux couleurs de la marque, pour l'en-tête d'une carte (position absolue dans un parent `relative`). */
export function BrandTint({ logo, strength = 0.16 }: { logo: LogoSpec; strength?: number }) {
  return (
    <svg className="absolute inset-0 size-full" preserveAspectRatio="none" aria-hidden="true">
      <rect width="100%" height="100%" fill={logo.tint} fillOpacity={strength} />
    </svg>
  );
}
