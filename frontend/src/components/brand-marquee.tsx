import type { LogoSpec } from "@/lib/logo";
import { BrandLogo } from "./brand-logo";

/** Bandeau défilant des marques proposées (pause au survol, figé si les animations sont réduites). */
export function BrandMarquee({ logos, label }: { logos: LogoSpec[]; label: string }) {
  if (logos.length < 4) return null;
  return (
    <div className="marquee-pause fade-x overflow-hidden py-6" aria-label={label}>
      <ul className="animate-marquee flex w-max items-center gap-12 pr-12">
        {[0, 1].flatMap((copy) =>
          logos.map((logo) => (
            <li key={`${copy}-${logo.label}`} aria-hidden={copy === 1} className="flex shrink-0 items-center gap-3">
              <BrandLogo logo={logo} size="sm" />
              <span translate="no" className="font-display text-sm font-bold text-ink/80">{logo.label}</span>
            </li>
          )),
        )}
      </ul>
    </div>
  );
}
