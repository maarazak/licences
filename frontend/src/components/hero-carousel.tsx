"use client";

import { ArrowLeft, ArrowRight, Pause, Play } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import type { LogoSpec } from "@/lib/logo";
import { BrandLogo, BrandTint } from "./brand-logo";
import { useI18n } from "./i18n";
import { Badge } from "./ui";

/** Textes déjà traduits côté serveur ; seuls des champs d'affichage sont transmis au navigateur. */
export type HeroSlide = { slug: string; title: string; duration: string; price: string; shelf: string; badge: string; badgeTone: "success" | "warning" | "danger"; logo: LogoSpec };

/** Position d'une carte selon son écart avec la carte active : centre, voisine de droite / de gauche, ou cachée. */
function position(offset: number, count: number): string {
  if (offset === 0) return "z-30 scale-100 rotate-0 opacity-100";
  if (offset === 1) return "z-20 translate-x-[58%] scale-[0.88] rotate-[5deg] opacity-70 max-sm:pointer-events-none max-sm:opacity-0";
  if (offset === count - 1) return "z-20 -translate-x-[58%] scale-[0.88] -rotate-[5deg] opacity-70 max-sm:pointer-events-none max-sm:opacity-0";
  return "pointer-events-none z-10 scale-75 opacity-0";
}

const round = "flex size-10 items-center justify-center rounded-full border border-line bg-white text-ink shadow-sm transition hover:-translate-y-0.5 hover:shadow-card";

/** Carrousel des offres phares : cartes qui glissent, lecture automatique avec barre de progression, pause, flèches et points. */
export function HeroCarousel({ slides, cta }: { slides: HeroSlide[]; cta: string }) {
  const { t } = useI18n();
  const [index, setIndex] = useState(0);
  const [paused, setPaused] = useState(false);
  const [reducedMotion, setReducedMotion] = useState(true);
  const count = slides.length;

  useEffect(() => {
    const query = window.matchMedia("(prefers-reduced-motion: reduce)");
    const update = () => setReducedMotion(query.matches);
    update();
    query.addEventListener("change", update);
    return () => query.removeEventListener("change", update);
  }, []);

  const playing = !paused && !reducedMotion && count > 1;
  useEffect(() => {
    if (!playing) return;
    const timer = setInterval(() => setIndex((i) => (i + 1) % count), 4500);
    return () => clearInterval(timer);
  }, [playing, count]);

  if (!count) return null;
  const floating = [slides[3], slides[4], slides[5]].filter(Boolean);

  return (
    <div role="group" aria-roledescription={t("carrousel")} aria-label={t("Offres à la une")} className="relative">
      <div aria-hidden="true" className="absolute -inset-6 -z-10 rounded-full bg-brand-bright/15 blur-3xl" />
      {floating[0] ? <BrandLogo logo={floating[0].logo} size="md" className="animate-float absolute -top-3 left-0 z-40 hidden sm:flex" /> : null}
      {floating[1] ? <BrandLogo logo={floating[1].logo} size="md" className="animate-float-slow absolute right-0 bottom-24 z-40 hidden sm:flex" /> : null}
      {floating[2] ? <BrandLogo logo={floating[2].logo} size="sm" className="animate-float absolute top-1 right-8 z-40 hidden sm:flex" /> : null}

      <div className="relative mx-auto h-[25rem] w-full max-w-[34rem]" aria-live={playing ? "off" : "polite"}>
        {slides.map((slide, i) => {
          const offset = (i - index + count) % count;
          const active = offset === 0;
          return (
            <Link
              key={slide.slug}
              href={`/boutique/${slide.slug}`}
              tabIndex={active ? 0 : -1}
              aria-hidden={!active}
              className={`absolute inset-x-0 top-2 mx-auto block w-[17rem] overflow-hidden rounded-3xl bg-white text-ink shadow-[0_30px_60px_-24px_rgb(11_18_32/0.4)] ring-1 ring-line transition duration-700 ease-out sm:w-72 ${position(offset, count)}`}
            >
              <div className="relative h-32">
                <BrandTint logo={slide.logo} strength={0.2} />
                <div aria-hidden="true" className="absolute inset-0 bg-gradient-to-b from-white/0 to-white/80" />
                {slide.shelf ? <span className="absolute top-4 left-4 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-bold text-ink/70 shadow-sm">{slide.shelf}</span> : null}
                <BrandLogo logo={slide.logo} size="xl" className="absolute -bottom-10 left-5" />
              </div>
              <div className="px-5 pt-12 pb-5">
                <p translate="no" className="font-display text-lg leading-snug font-extrabold">{slide.title}</p>
                <p className="mt-1 text-xs font-medium text-muted">{slide.duration}</p>
                <div className="mt-3">
                  <Badge tone={slide.badgeTone}>{slide.badge}</Badge>
                </div>
                <div className="mt-4 flex items-center justify-between gap-3 border-t border-line pt-4">
                  <p className="font-display text-xl font-extrabold">{slide.price}</p>
                  <span className="btn btn-primary btn-sm pointer-events-none whitespace-nowrap">
                    {cta}
                    <ArrowRight className="size-4" aria-hidden="true" />
                  </span>
                </div>
              </div>
            </Link>
          );
        })}
      </div>

      {count > 1 ? (
        <div className="mx-auto mt-2 max-w-[34rem] space-y-4">
          <div className="h-1 overflow-hidden rounded-full bg-ink/10" aria-hidden="true">
            {playing || paused ? <div key={index} className={`animate-progress h-full rounded-full bg-gradient-to-r from-brand-bright to-accent ${paused ? "[animation-play-state:paused]" : ""}`} /> : null}
          </div>
          <div className="flex items-center justify-between gap-3">
            <div className="flex items-center gap-1">
              {slides.map((slide, i) => (
                <button key={slide.slug} type="button" aria-label={t("Offre {i} sur {n} : {title}", { i: i + 1, n: count, title: slide.title })} aria-current={i === index} onClick={() => setIndex(i)} className="flex size-6 items-center justify-center">
                  <span className={`block rounded-full transition-all duration-300 ${i === index ? "h-2 w-6 bg-ink" : "size-2 bg-ink/20 hover:bg-ink/40"}`} />
                </button>
              ))}
            </div>
            <div className="flex items-center gap-2">
              <button type="button" aria-label={t("Offre précédente")} onClick={() => setIndex((i) => (i - 1 + count) % count)} className={round}>
                <ArrowLeft className="size-4" aria-hidden="true" />
              </button>
              <button type="button" onClick={() => setPaused((p) => !p)} aria-pressed={paused} aria-label={paused || reducedMotion ? t("Lecture") : t("Pause")} className={round}>
                {paused || reducedMotion ? <Play className="size-4" aria-hidden="true" /> : <Pause className="size-4" aria-hidden="true" />}
              </button>
              <button type="button" aria-label={t("Offre suivante")} onClick={() => setIndex((i) => (i + 1) % count)} className={round}>
                <ArrowRight className="size-4" aria-hidden="true" />
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
