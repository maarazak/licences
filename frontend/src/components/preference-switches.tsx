"use client";

import { useTransition } from "react";
import { setCurrencyAction, setLangAction } from "@/server/actions/shop";

/**
 * Sélecteurs de langue et de devise. Après l'enregistrement du choix, la page est rechargée en entier :
 * c'est la seule façon fiable d'afficher tous les textes dans la nouvelle langue, même si le navigateur ou
 * une extension a modifié la page (traduction automatique, correcteur…).
 */
export function LanguageSelect({ lang, options, label, className }: { lang: string; options: Array<{ value: string; label: string }>; label: string; className?: string }) {
  const [pending, start] = useTransition();
  return (
    <select
      key={lang}
      aria-label={label}
      defaultValue={lang}
      disabled={pending}
      className={className}
      onChange={(event) => {
        const data = new FormData();
        data.set("lang", event.target.value);
        start(async () => {
          await setLangAction(data);
          window.location.reload();
        });
      }}
    >
      {options.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  );
}

export function CurrencyToggle({ currency, label, options }: { currency: string; label: string; options: Array<{ value: string; label: string }> }) {
  const [pending, start] = useTransition();
  return (
    <div role="group" aria-label={label} className="flex rounded-full border border-line bg-white p-0.5 shadow-sm">
      {options.map((o) => (
        <button
          key={o.value}
          type="button"
          aria-pressed={currency === o.value}
          disabled={pending}
          onClick={() => {
            if (currency === o.value) return;
            const data = new FormData();
            data.set("currency", o.value);
            start(async () => {
              await setCurrencyAction(data);
              window.location.reload();
            });
          }}
          className={`min-h-8 cursor-pointer rounded-full px-3 text-xs font-bold transition ${currency === o.value ? "bg-ink text-white" : "text-ink/70 hover:text-ink"}`}
        >
          {o.label}
        </button>
      ))}
    </div>
  );
}
