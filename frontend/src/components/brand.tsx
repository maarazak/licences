import { SITE_NAME } from "@/lib/site";

/**
 * Monogramme : un « L » en forme de ticket (encoches latérales), vert du thème,
 * avec une pastille orange. Même dessin que `public/brand/licences-mark.svg`.
 */
export function LogoMark({ size = 32, className = "" }: { size?: number; className?: string }) {
  return (
    <svg width={size} height={size} viewBox="0 0 48 48" aria-hidden="true" focusable="false" className={className}>
      <path d="M12 0H36A12 12 0 0 1 48 12V19.5A4.5 4.5 0 0 0 48 28.5V36A12 12 0 0 1 36 48H12A12 12 0 0 1 0 36V28.5A4.5 4.5 0 0 0 0 19.5V12A12 12 0 0 1 12 0Z" fill="#0d5c4a" />
      <path d="M16 12H23V29.5H33V36H16Z" fill="#ffffff" />
      <circle cx="29.5" cy="15.5" r="3.5" fill="#e8590c" />
    </svg>
  );
}

/** Monogramme + nom de la boutique. */
export function LogoLockup({ size = 34, tone = "dark" }: { size?: number; tone?: "dark" | "light" }) {
  return (
    <span className="flex items-center gap-2.5">
      <LogoMark size={size} />
      <span className={`font-display text-xl font-extrabold tracking-tight ${tone === "light" ? "text-white" : "text-ink"}`}>{SITE_NAME}</span>
    </span>
  );
}
