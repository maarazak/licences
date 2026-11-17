import { ArrowRight, type LucideIcon } from "lucide-react";
import Link from "next/link";
import type { Tone } from "@/lib/enums";

export function Badge({ tone = "neutral", children }: { tone?: Tone; children: React.ReactNode }) {
  return <span className={`badge badge-${tone}`}>{children}</span>;
}

export function PageHeader({ title, description, actions }: { title: string; description?: string; actions?: React.ReactNode }) {
  return (
    <div className="flex flex-wrap items-end justify-between gap-4">
      <div className="min-w-0">
        <h1 className="h1">{title}</h1>
        {description ? <p className="lead mt-2 max-w-2xl">{description}</p> : null}
      </div>
      {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
    </div>
  );
}

export function EmptyState({ title, description, action }: { title: string; description?: string; action?: { href: string; label: string } }) {
  return (
    <div className="card px-6 py-10 text-center">
      <p className="h3">{title}</p>
      {description ? <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">{description}</p> : null}
      {action ? (
        <Link href={action.href} className="btn btn-soft mt-5">
          {action.label}
          <ArrowRight className="size-4" aria-hidden="true" />
        </Link>
      ) : null}
    </div>
  );
}

export function Stat({ label, value, hint, href, Icon }: { label: string; value: string; hint?: string; href?: string; Icon?: LucideIcon }) {
  const content = (
    <>
      {Icon ? (
        <span className="mb-3 flex size-9 items-center justify-center rounded-xl bg-brand-soft text-brand">
          <Icon className="size-4.5" aria-hidden="true" />
        </span>
      ) : null}
      <p className="text-xs font-semibold text-muted">{label}</p>
      <p className="mt-1 font-display text-2xl font-bold tracking-tight text-ink">{value}</p>
      {hint ? <p className="mt-1 text-xs text-muted">{hint}</p> : null}
    </>
  );
  return href ? (
    <Link href={href} className="card block p-4 transition duration-300 hover:-translate-y-0.5 hover:shadow-pop">
      {content}
    </Link>
  ) : (
    <div className="card p-4">{content}</div>
  );
}

/** Liste « terme : valeur » pour les récapitulatifs. */
export function Details({ rows }: { rows: Array<[string, React.ReactNode]> }) {
  return (
    <dl className="divide-y divide-line text-sm">
      {rows.map(([term, value]) => (
        <div key={term} className="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
          <dt className="text-muted">{term}</dt>
          <dd className="min-w-0 text-right font-medium text-ink [overflow-wrap:anywhere]">{value}</dd>
        </div>
      ))}
    </dl>
  );
}
