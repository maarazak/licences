"use client";

import { ChevronDown, Search, X } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useId, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";

export type SearchItem = { slug: string; title: string; shelf: string };
export type SearchLabels = {
  placeholder: string;
  search: string;
  openList: string;
  close: string;
  none: string;
  all: string;
  searchFor: string;
};

const norm = (value: string) => value.normalize("NFD").replace(/\p{M}/gu, "").toLowerCase();

/** Recherche sans accent ni casse, sur le nom du produit et son rayon. */
function useMatches(items: SearchItem[], query: string) {
  const index = useMemo(() => items.map((item) => ({ item, key: norm(`${item.title} ${item.shelf}`) })), [items]);
  return useMemo(() => {
    const q = norm(query.trim());
    return (q ? index.filter((entry) => entry.key.includes(q)) : index).map((entry) => entry.item);
  }, [index, query]);
}

const searchHref = (query: string) => (query.trim() ? `/boutique?q=${encodeURIComponent(query.trim().slice(0, 80))}` : "/boutique");

/**
 * Recherche de l'en-tête pour ordinateur : champ de saisie + liste déroulante des produits (nom, rayon).
 * On tape pour filtrer ou on ouvre la liste avec la flèche pour choisir un produit ; Entrée sans sélection lance la recherche.
 */
export function DesktopSearch({ items, labels }: { items: SearchItem[]; labels: SearchLabels }) {
  const router = useRouter();
  const id = useId();
  const [query, setQuery] = useState("");
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const matches = useMatches(items, query);
  const listId = `${id}-list`;

  useEffect(() => {
    if (active >= 0) document.getElementById(`${id}-opt-${active}`)?.scrollIntoView({ block: "nearest" });
  }, [active, id]);

  const pick = (slug: string) => {
    setOpen(false);
    setQuery("");
    setActive(-1);
    router.push(`/boutique/${encodeURIComponent(slug)}`);
  };

  return (
    <form
      role="search"
      className="relative w-full"
      onSubmit={(event) => {
        event.preventDefault();
        setOpen(false);
        router.push(searchHref(query));
      }}
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget)) setOpen(false);
      }}
    >
      <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
      <input
        type="search"
        role="combobox"
        aria-label={labels.search}
        aria-expanded={open}
        aria-controls={listId}
        aria-autocomplete="list"
        aria-activedescendant={open && active >= 0 ? `${id}-opt-${active}` : undefined}
        autoComplete="off"
        maxLength={80}
        placeholder={labels.placeholder}
        value={query}
        className="input min-h-10 rounded-full border-line bg-canvas pr-11 pl-10 text-sm shadow-none"
        onFocus={() => setOpen(true)}
        onChange={(event) => {
          setQuery(event.target.value);
          setActive(-1);
          setOpen(true);
        }}
        onKeyDown={(event) => {
          if (event.key === "ArrowDown") {
            event.preventDefault();
            setOpen(true);
            setActive((a) => Math.min(a + 1, matches.length - 1));
          } else if (event.key === "ArrowUp") {
            event.preventDefault();
            setActive((a) => Math.max(a - 1, -1));
          } else if (event.key === "Escape") {
            setOpen(false);
          } else if (event.key === "Enter" && open && active >= 0 && matches[active]) {
            event.preventDefault();
            pick(matches[active].slug);
          }
        }}
      />
      <button type="button" aria-label={labels.openList} aria-expanded={open} aria-controls={listId} onClick={() => setOpen((v) => !v)} className="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-full text-muted transition hover:text-ink">
        <ChevronDown className={`size-4 transition ${open ? "rotate-180" : ""}`} aria-hidden="true" />
      </button>
      {open ? (
        <div className="absolute top-[calc(100%+0.5rem)] left-0 z-50 w-[28rem] max-w-[calc(100vw-2rem)] min-w-full overflow-hidden rounded-2xl border border-line bg-white shadow-pop">
          <ul id={listId} role="listbox" aria-label={labels.all} className="max-h-80 overflow-y-auto overscroll-contain py-1.5">
            {matches.length ? (
              matches.map((item, i) => (
                <li
                  key={item.slug}
                  id={`${id}-opt-${i}`}
                  role="option"
                  aria-selected={i === active}
                  onMouseDown={(event) => event.preventDefault()}
                  onMouseEnter={() => setActive(i)}
                  onClick={() => pick(item.slug)}
                  className={`flex cursor-pointer items-center justify-between gap-3 px-4 py-2.5 text-sm ${i === active ? "bg-brand-soft" : ""}`}
                >
                  <span translate="no" className="truncate font-semibold text-ink">
                    {item.title}
                  </span>
                  {item.shelf ? <span className="shrink-0 text-xs text-muted">{item.shelf}</span> : null}
                </li>
              ))
            ) : (
              <li role="presentation" className="px-4 py-3 text-sm text-muted">
                {labels.none}
              </li>
            )}
          </ul>
        </div>
      ) : null}
    </form>
  );
}

/**
 * Recherche pour téléphone : une loupe dans l'en-tête ouvre un écran complet (champ en haut, liste de produits en
 * grandes lignes tactiles). Rendu dans <body> : l'en-tête a un flou d'arrière-plan qui piégerait un élément fixe.
 */
export function MobileSearch({ items, labels }: { items: SearchItem[]; labels: SearchLabels }) {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const matches = useMatches(items, query);
  const closeRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) return;
    const previous = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(false);
    };
    document.addEventListener("keydown", onKey);
    return () => {
      document.body.style.overflow = previous;
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  const finish = () => {
    setOpen(false);
    setQuery("");
  };

  return (
    <>
      <button type="button" aria-label={labels.search} aria-haspopup="dialog" onClick={() => setOpen(true)} className="flex size-11 items-center justify-center rounded-full border border-line bg-white text-ink shadow-sm transition active:bg-ink/5">
        <Search className="size-5" aria-hidden="true" />
      </button>
      {open
        ? createPortal(
            <div role="dialog" aria-modal="true" aria-label={labels.search} className="fixed inset-0 z-[60] flex flex-col bg-white">
              <div className="flex items-center gap-2 border-b border-line p-3">
                <form
                  role="search"
                  className="relative min-w-0 flex-1"
                  onSubmit={(event) => {
                    event.preventDefault();
                    const target = searchHref(query);
                    finish();
                    router.push(target);
                  }}
                >
                  <Search className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted" aria-hidden="true" />
                  <input type="search" autoFocus enterKeyHint="search" aria-label={labels.search} autoComplete="off" maxLength={80} placeholder={labels.placeholder} value={query} onChange={(event) => setQuery(event.target.value)} className="input min-h-12 rounded-full bg-canvas pl-12" />
                </form>
                <button ref={closeRef} type="button" onClick={finish} aria-label={labels.close} className="flex size-12 shrink-0 items-center justify-center rounded-full text-ink transition active:bg-ink/5">
                  <X className="size-6" aria-hidden="true" />
                </button>
              </div>
              <div className="flex-1 overflow-y-auto overscroll-contain pb-6">
                {query.trim() ? (
                  <Link href={searchHref(query)} onClick={finish} className="flex min-h-14 items-center gap-3 border-b border-line px-4 text-sm font-bold text-brand active:bg-ink/5">
                    <Search className="size-4 shrink-0" aria-hidden="true" />
                    <span className="truncate">{labels.searchFor.replace("{q}", query.trim().slice(0, 40))}</span>
                  </Link>
                ) : (
                  <p className="px-4 pt-4 pb-1 text-xs font-bold tracking-[0.14em] text-muted uppercase">{labels.all}</p>
                )}
                {matches.length ? (
                  <ul>
                    {matches.map((item) => (
                      <li key={item.slug}>
                        <Link href={`/boutique/${encodeURIComponent(item.slug)}`} onClick={finish} className="flex min-h-14 items-center justify-between gap-3 border-b border-line/60 px-4 active:bg-ink/5">
                          <span translate="no" className="min-w-0 truncate text-[15px] font-semibold text-ink">
                            {item.title}
                          </span>
                          {item.shelf ? <span className="shrink-0 rounded-full bg-canvas px-2.5 py-1 text-[11px] font-bold text-muted">{item.shelf}</span> : null}
                        </Link>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="px-4 py-8 text-center text-sm text-muted">{labels.none}</p>
                )}
              </div>
            </div>,
            document.body,
          )
        : null}
    </>
  );
}
