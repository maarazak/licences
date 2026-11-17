"use client";

import { ChevronDown } from "lucide-react";
import Link, { useLinkStatus } from "next/link";
import { Spinner } from "./forms";

function Content({ label, hint }: { label: string; hint: string }) {
  const { pending } = useLinkStatus();
  return (
    <>
      {pending ? <Spinner /> : <ChevronDown className="size-4" aria-hidden="true" />}
      <span>{label}</span>
      <span className="text-xs font-medium text-muted">· {hint}</span>
    </>
  );
}

/**
 * « Charger plus » : recharge la page avec davantage d'offres affichées, sans remonter en haut (scroll conservé) ;
 * un cercle tourne pendant le chargement. Lien classique : fonctionne aussi sans JavaScript et conserve les filtres.
 */
export function LoadMoreLink({ href, label, hint }: { href: string; label: string; hint: string }) {
  return (
    <div className="mt-8 flex justify-center">
      <Link href={href} scroll={false} prefetch={false} className="btn btn-soft min-h-12 w-full max-w-sm text-sm">
        <Content label={label} hint={hint} />
      </Link>
    </div>
  );
}
