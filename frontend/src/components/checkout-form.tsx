"use client";

import { ArrowRight, Lock } from "lucide-react";
import Link from "next/link";
import { useState, useSyncExternalStore } from "react";
import { COUNTRIES, type CountryCode, detectUserCountry, getCountry, validatePhoneForCountry } from "@/lib/countries";
import { MAX_QUANTITY } from "@/lib/enums";
import { formatXof } from "@/lib/format";
import { checkoutAction } from "@/server/actions/shop";
import { BrandLogo } from "./brand-logo";
import { FormMessage, SubmitButton, useKeepOnError } from "./forms";
import { useI18n } from "./i18n";
import type { LogoSpec } from "@/lib/logo";

export type CheckoutFormProps = {
  product: { id: string; title: string; unitPriceXof: number; maxQuantity: number; requiresEmail: boolean; accessLabel: string; durationLabel: string; logo: LogoSpec };
  defaults: { fullName?: string; firstName?: string; lastName?: string; phone: string; country: string; email: string };
  balanceXof: number;
  terms?: string[];
};

const emptySubscribe = () => () => {};

export function CheckoutForm({ product, defaults, balanceXof, clientToken }: CheckoutFormProps & { clientToken: string }) {
  const { t, money, currency, rate } = useI18n();
  const [state, formAction, formRef] = useKeepOnError(checkoutAction);
  const [quantity, setQuantity] = useState(1);
  const [payChoice, setPayChoice] = useState<"gateway" | "balance">("gateway");

  const detectedCountry = useSyncExternalStore(
    emptySubscribe,
    () => detectUserCountry("BF"),
    () => "BF" as CountryCode,
  );

  const [manualCountry, setManualCountry] = useState<CountryCode | null>(null);
  const selectedCountry = manualCountry ?? (defaults.country && COUNTRIES.some((c) => c.code === defaults.country) ? (defaults.country as CountryCode) : detectedCountry);
  // Le champ reste vierge par défaut : seul le placeholder guide l'utilisateur
  const [phoneInput, setPhoneInput] = useState("");

  const currentCountry = getCountry(selectedCountry);
  const phoneDigits = phoneInput.replace(/[^0-9]/g, "");
  const phoneValidation = validatePhoneForCountry(phoneInput, selectedCountry);

  const total = product.unitPriceXof * quantity;
  const canUseBalance = balanceXof >= total;
  // Si le solde ne suffit plus (quantité augmentée), on repasse explicitement à la passerelle.
  const payWith = canUseBalance ? payChoice : "gateway";
  const maxQuantity = Math.max(1, Math.min(MAX_QUANTITY, product.maxQuantity));

  return (
    <form ref={formRef} action={formAction} className="grid gap-8 lg:grid-cols-[1fr_360px] lg:items-start">
      <input type="hidden" name="productId" value={product.id} />
      <input type="hidden" name="expectedTotalXof" value={total} />
      <input type="hidden" name="clientToken" value={clientToken} />

      <div className="min-w-0 space-y-8">
        <section className="card p-5 sm:p-6">
          <h2 className="h3 text-lg">{t("Vos coordonnées")}</h2>
          <p className="hint">{t("Transmises au prestataire de paiement pour cet achat.")}</p>
          <p className="mt-3 text-sm [overflow-wrap:anywhere]">
            <span className="text-muted">{t("Compte")} : </span>
            <span className="font-semibold">{defaults.email}</span>
          </p>
          <div className="mt-4 space-y-4">
            <div>
              <label htmlFor="fullName" className="label">
                {t("Nom complet")} <span className="text-brand">*</span>
              </label>
              <input
                id="fullName"
                name="fullName"
                required
                defaultValue={defaults.fullName || [defaults.firstName, defaults.lastName].filter(Boolean).join(" ")}
                autoComplete="name"
                maxLength={150}
                placeholder={t("Ex. Awa Traoré")}
                className="input"
              />
            </div>

            <div>
              <label htmlFor="phone" className="label">
                {t("Numéro de téléphone Mobile Money")} <span className="text-brand">*</span>
              </label>
              <div className="relative flex rounded-xl border border-line bg-canvas transition focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/20">
                <select
                  id="country"
                  name="country"
                  value={selectedCountry}
                  onChange={(e) => {
                    setManualCountry(e.target.value as CountryCode);
                  }}
                  aria-label={t("Indicatif pays")}
                  className="h-11 max-w-[145px] sm:max-w-[185px] shrink-0 cursor-pointer rounded-l-xl border-r border-line bg-canvas pl-3 pr-2 text-sm font-medium text-ink focus:outline-none"
                >
                  {COUNTRIES.map((c) => (
                    <option key={c.code} value={c.code}>
                      {c.flag} {c.dial} ({c.name})
                    </option>
                  ))}
                </select>
                <input
                  id="phone"
                  name="phone"
                  type="tel"
                  required
                  value={phoneInput}
                  onChange={(e) => setPhoneInput(e.target.value)}
                  autoComplete="tel-national"
                  inputMode="numeric"
                  placeholder={currentCountry.placeholder}
                  className="h-11 w-full rounded-r-xl bg-transparent px-3.5 text-sm text-ink placeholder:text-muted/60 focus:outline-none"
                />
              </div>
              <div className="mt-1.5 flex flex-wrap items-center justify-between gap-1 text-xs text-muted">
                <span>
                  {t("Pays :")} <strong className="font-medium text-ink">{currentCountry.name}</strong> ({currentCountry.dial}) · {currentCountry.minDigits === currentCountry.maxDigits ? t("{count} chiffres attendus", { count: currentCountry.minDigits }) : t("{min} à {max} chiffres attendus", { min: currentCountry.minDigits, max: currentCountry.maxDigits })}
                </span>
                {phoneDigits.length > 0 && (
                  <span className={phoneValidation.valid ? "font-semibold text-emerald-600" : "font-medium text-amber-600"}>
                    {phoneDigits.length}/{currentCountry.minDigits} {phoneValidation.valid ? "✓" : ""}
                  </span>
                )}
              </div>
              {!phoneValidation.valid && phoneDigits.length > 0 && (
                <p className="mt-1 text-xs text-amber-700">{phoneValidation.message}</p>
              )}
            </div>
          </div>
        </section>

        <section className="card p-5 sm:p-6">
          <h2 className="h3 text-lg">{t("Quantité")}</h2>
          <div className="mt-3 flex items-center gap-2 max-w-44">
            <button
              type="button"
              onClick={() => setQuantity((q) => Math.max(1, q - 1))}
              disabled={quantity <= 1}
              className="flex size-11 shrink-0 items-center justify-center rounded-xl border border-line bg-canvas text-base font-bold text-ink transition hover:border-brand/40 disabled:opacity-40"
              aria-label={t("Diminuer la quantité")}
            >
              −
            </button>
            <input
              id="quantity"
              name="quantity"
              type="number"
              min={1}
              max={maxQuantity}
              step={1}
              required
              value={quantity}
              onChange={(e) => {
                const val = parseInt(e.target.value, 10);
                if (isNaN(val) || val < 1) {
                  setQuantity(1);
                } else {
                  setQuantity(Math.min(val, maxQuantity));
                }
              }}
              className="input h-11 text-center text-base font-semibold"
            />
            <button
              type="button"
              onClick={() => setQuantity((q) => Math.min(maxQuantity, q + 1))}
              disabled={quantity >= maxQuantity}
              className="flex size-11 shrink-0 items-center justify-center rounded-xl border border-line bg-canvas text-base font-bold text-ink transition hover:border-brand/40 disabled:opacity-40"
              aria-label={t("Augmenter la quantité")}
            >
              +
            </button>
          </div>

          {product.requiresEmail ? (
            <div className="mt-6">
              <h3 className="h3">{quantity > 1 ? t("Adresses e-mail à activer") : t("Adresse e-mail à activer")}</h3>
              <p className="hint">{t("Cette offre s'active sur une adresse e-mail. Indiquez-en une par unité, toutes différentes. Vérifiez l'orthographe : elle ne peut pas être changée après le paiement.")}</p>
              <div className="mt-3 space-y-3">
                {Array.from({ length: quantity }, (_, i) => (
                  <div key={i}>
                    <label htmlFor={`activation-${i}`} className="sr-only">
                      {t("Adresse e-mail à activer n° {n}", { n: i + 1 })}
                    </label>
                    <input id={`activation-${i}`} name="activationEmail" type="email" required maxLength={191} defaultValue={i === 0 ? defaults.email : ""} placeholder={t("Adresse n° {n}", { n: i + 1 })} className="input" />
                  </div>
                ))}
              </div>
            </div>
          ) : null}
        </section>

        <section className="card p-5 sm:p-6">
          <h2 className="h3 text-lg">{t("Moyen de paiement")}</h2>
          <div className="mt-3 space-y-2.5">
            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-3.5 has-[:checked]:border-brand has-[:checked]:bg-brand-soft/50">
              <input
                type="radio"
                name="payWith"
                value="gateway"
                checked={payWith === "gateway"}
                onChange={() => setPayChoice("gateway")}
                className="mt-1 size-4 accent-brand"
              />
              <span className="text-sm">
                <span className="font-semibold">{t("Mobile Money")}</span>
                <span className="block text-muted">{t("Orange Money, Moov, MTN, Wave selon votre pays.")}</span>
              </span>
            </label>
            <label
              className={`flex items-start gap-3 rounded-xl border border-line p-3.5 has-[:checked]:border-brand has-[:checked]:bg-brand-soft/50 ${
                canUseBalance ? "cursor-pointer" : "cursor-not-allowed bg-canvas-subtle/50 opacity-60"
              }`}
            >
              <input
                type="radio"
                name="payWith"
                value="balance"
                checked={payWith === "balance"}
                onChange={() => setPayChoice("balance")}
                disabled={!canUseBalance}
                className="mt-1 size-4 accent-brand disabled:cursor-not-allowed"
              />
              <span className="text-sm">
                <span className="font-semibold">
                  {t("Mon solde")} ({formatXof(balanceXof)})
                </span>
                <span className="block text-muted">
                  {canUseBalance
                    ? t("Le solde paie la commande entière.")
                    : t("Solde insuffisant pour cette commande.")}
                </span>
              </span>
            </label>
          </div>
        </section>
      </div>

      <aside className="card space-y-4 p-5 shadow-pop lg:sticky lg:top-24">
        <div className="flex items-center gap-3.5">
          <BrandLogo logo={product.logo} size="md" />
          <div className="min-w-0">
            <p className="h3 leading-snug">{product.title}</p>
            <p className="mt-0.5 text-xs text-muted">
              {product.durationLabel} · {product.accessLabel} · {t("Quantité")} {quantity}
            </p>
          </div>
        </div>
        <div className="flex items-baseline justify-between border-t border-line pt-4">
          <span className="text-sm font-semibold">{t("Total")}</span>
          <span className="font-display text-3xl font-extrabold tracking-tight">{money(total)}</span>
        </div>
        {currency === "USD" && rate ? (
          <p className="-mt-2 rounded-xl bg-canvas px-3.5 py-2.5 text-xs leading-relaxed text-muted">
            <span className="font-bold text-ink">{t("Vous paierez {amount}", { amount: formatXof(total) })}</span> · {t("Taux du jour : 1 $ = {rate} FCFA", { rate: Math.round(rate) })}
          </p>
        ) : null}
        <FormMessage state={state} />
        <SubmitButton className="btn btn-primary btn-block min-h-12 text-base" pendingLabel={t("Vérification de l'offre…")}>
          {t("Passer au paiement")}
          <ArrowRight className="size-4" aria-hidden="true" />
        </SubmitButton>
        <p className="text-center text-xs leading-relaxed text-muted">
          {t("En validant votre commande, vous acceptez nos")}{" "}
          <Link href="/conditions" target="_blank" className="underline hover:text-ink">
            {t("conditions de vente")}
          </Link>
          .
        </p>
        <p className="flex items-start gap-2 text-xs leading-relaxed text-muted">
          <Lock className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          {t("Le prix est celui enregistré à la validation. Vous choisirez votre moyen de paiement sur la page sécurisée du prestataire.")}
        </p>
      </aside>
    </form>
  );
}
