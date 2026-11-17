/**
 * Logos des moyens de paiement (fichiers locaux dans public/payments : pictogrammes des marques, utilisés uniquement
 * pour indiquer les moyens de paiement acceptés). Aucun style en ligne (CSP de production).
 */
export type PayMethod = "orange" | "moov" | "wave" | "usdt";

/** `pad` : marge intérieure de la tuile (les PNG sont déjà cadrés ; le SVG USDT a besoin de plus d'air). */
const METHODS: Record<PayMethod, { src: string; label: string; pad: string }> = {
  orange: { src: "/payments/orange-money.png", label: "Orange Money", pad: "p-[3%]" },
  moov: { src: "/payments/moov-money.png", label: "Moov Money", pad: "p-[3%]" },
  wave: { src: "/payments/wave.png", label: "Wave", pad: "p-[6%]" },
  usdt: { src: "/payments/usdt.svg", label: "USDT", pad: "p-[14%]" },
};

/** Petite tuile blanche portant le logo. */
function Tile({ method, className = "" }: { method: PayMethod; className?: string }) {
  const { src, label, pad } = METHODS[method];
  return (
    <span className={`flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white shadow-logo ring-1 ring-black/5 ${className}`} role="img" aria-label={label} title={label}>
      {/* eslint-disable-next-line @next/next/no-img-element -- petit logo local déposé dans public/payments */}
      <img src={src} alt="" width={96} height={96} loading="lazy" decoding="async" className={`size-full object-contain ${pad}`} />
    </span>
  );
}

/** Miniatures flottantes (accueil) : Orange Money, Moov Money, Wave et USDT, discrètes, qui flottent comme les autres éléments. */
export function PayLogosFloating() {
  return (
    <div className="flex items-center gap-2" aria-label="Orange Money, Moov Money, Wave, USDT" role="group">
      <Tile method="orange" className="animate-float-sm size-9 sm:size-10" />
      <Tile method="moov" className="animate-float-sm-slow size-9 sm:size-10" />
      <Tile method="wave" className="animate-float-sm size-9 sm:size-10 [animation-delay:-3s]" />
      <Tile method="usdt" className="animate-float-sm-slow size-9 sm:size-10 [animation-delay:-1.5s]" />
    </div>
  );
}

/** Pied de page : logo + nom de chaque moyen de paiement. */
export function PayLogosRow({ methods = ["orange", "moov", "wave", "usdt"] as PayMethod[] }: { methods?: PayMethod[] }) {
  return (
    <ul className="flex flex-wrap items-center gap-x-4 gap-y-3">
      {methods.map((method) => (
        <li key={method} className="flex items-center gap-2">
          <Tile method={method} className="size-8" />
          <span className="text-xs font-semibold text-ink/75">{METHODS[method].label}</span>
        </li>
      ))}
    </ul>
  );
}
