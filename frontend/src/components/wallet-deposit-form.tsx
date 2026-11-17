"use client";

import { Lock, PlusCircle } from "lucide-react";
import { useState, useSyncExternalStore } from "react";
import { COUNTRIES, type CountryCode, detectUserCountry, getCountry, validatePhoneForCountry } from "@/lib/countries";
import { formatXof } from "@/lib/format";
import { startWalletDepositAction } from "@/server/actions/shop";
import { FormMessage, SubmitButton, useKeepOnError } from "./forms";
import { useI18n } from "./i18n";

export type WalletDepositFormProps = {
  defaults: {
    fullName?: string;
    phone?: string;
    country?: string;
  };
};

const PRESETS = [2500, 5000, 10000, 25000, 50000];
const emptySubscribe = () => () => {};

export function WalletDepositForm({ defaults }: WalletDepositFormProps) {
  const { t } = useI18n();
  const [state, formAction, formRef] = useKeepOnError(startWalletDepositAction);
  const [amount, setAmount] = useState<string>("5000");

  const detectedCountry = useSyncExternalStore(
    emptySubscribe,
    () => detectUserCountry("BF"),
    () => "BF" as CountryCode,
  );

  const [manualCountry, setManualCountry] = useState<CountryCode | null>(null);
  const selectedCountry =
    manualCountry ??
    (defaults.country && COUNTRIES.some((c) => c.code === defaults.country)
      ? (defaults.country as CountryCode)
      : detectedCountry);

  const [phoneInput, setPhoneInput] = useState<string>(defaults.phone || "");

  const currentCountry = getCountry(selectedCountry);
  const phoneDigits = phoneInput.replace(/[^0-9]/g, "");
  const phoneValidation = validatePhoneForCountry(phoneInput, selectedCountry);

  const numAmount = parseInt(amount, 10) || 0;
  const isAmountValid = numAmount >= 500 && numAmount <= 1000000;

  return (
    <section className="card overflow-hidden p-6 shadow-sm border border-line">
      <div className="flex items-center gap-3">
        <span className="flex size-10 items-center justify-center rounded-xl bg-brand/10 text-brand">
          <PlusCircle className="size-5" aria-hidden="true" />
        </span>
        <div>
          <h2 className="h3 text-lg font-bold">{t("Recharger mon solde")}</h2>
          <p className="text-xs text-muted">
            {t("Dépôt sécurisé par Mobile Money. Votre solde est crédité instantanément.")}
          </p>
        </div>
      </div>

      <form ref={formRef} action={formAction} className="mt-5 space-y-5">
        {/* Choix du montant */}
        <div>
          <label htmlFor="amountXof" className="label text-xs font-semibold uppercase tracking-wider text-muted">
            {t("Montant à recharger")} <span className="text-brand">*</span>
          </label>

          {/* Boutons montants rapides */}
          <div className="mt-1.5 flex flex-wrap gap-2">
            {PRESETS.map((p) => {
              const active = numAmount === p;
              return (
                <button
                  key={p}
                  type="button"
                  onClick={() => setAmount(String(p))}
                  className={`rounded-lg px-3 py-1.5 text-xs font-medium transition ${
                    active
                      ? "bg-brand text-white shadow-sm"
                      : "bg-canvas border border-line text-ink hover:border-brand/40"
                  }`}
                >
                  {formatXof(p)}
                </button>
              );
            })}
          </div>

          <div className="relative mt-2.5">
            <input
              id="amountXof"
              name="amountXof"
              type="number"
              min={500}
              max={1000000}
              step={100}
              required
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              placeholder="Ex. 5000"
              className="input pr-16 text-base font-semibold"
            />
            <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-bold text-muted">
              FCFA
            </span>
          </div>
          <p className="mt-1 text-xs text-muted">
            {t("Montant minimum : 500 FCFA · Maximum : 1 000 000 FCFA")}
          </p>
        </div>

        {/* Coordonnées de paiement Mobile Money */}
        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label htmlFor="fullName" className="label text-xs font-semibold uppercase tracking-wider text-muted">
              {t("Nom complet (optionnel)")}
            </label>
            <input
              id="fullName"
              name="fullName"
              defaultValue={defaults.fullName || ""}
              autoComplete="name"
              maxLength={150}
              placeholder={t("Ex. Awa Traoré")}
              className="input"
            />
          </div>

          <div>
            <label htmlFor="phone" className="label text-xs font-semibold uppercase tracking-wider text-muted">
              {t("Téléphone Mobile Money")} <span className="text-brand">*</span>
            </label>
            <div className="relative flex rounded-xl border border-line bg-canvas transition focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/20">
              <select
                id="country"
                name="country"
                value={selectedCountry}
                onChange={(e) => setManualCountry(e.target.value as CountryCode)}
                aria-label={t("Indicatif pays")}
                className="h-11 max-w-[140px] sm:max-w-[170px] shrink-0 cursor-pointer rounded-l-xl border-r border-line bg-canvas pl-2.5 pr-2 text-xs font-medium text-ink focus:outline-none"
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
                className="h-11 w-full rounded-r-xl bg-transparent px-3 text-sm text-ink placeholder:text-muted/60 focus:outline-none"
              />
            </div>
            <div className="mt-1 flex items-center justify-between text-xs text-muted">
              <span>
                {currentCountry.name} ({currentCountry.dial})
              </span>
              {phoneDigits.length > 0 && (
                <span className={phoneValidation.valid ? "font-semibold text-emerald-600" : "font-medium text-amber-600"}>
                  {phoneDigits.length}/{currentCountry.minDigits} {phoneValidation.valid ? "✓" : ""}
                </span>
              )}
            </div>
            {!phoneValidation.valid && phoneDigits.length > 0 && (
              <p className="mt-0.5 text-xs text-amber-700">{phoneValidation.message}</p>
            )}
          </div>
        </div>

        <FormMessage state={state} />

        <div className="pt-2">
          <SubmitButton
            disabled={!isAmountValid || !phoneValidation.valid}
            className="btn btn-brand w-full sm:w-auto flex items-center justify-center gap-2"
          >
            <Lock className="size-4" aria-hidden="true" />
            {t("Payer {amount} par Mobile Money", {
              amount: formatXof(numAmount || 0),
            })}
          </SubmitButton>
          <p className="mt-2 text-xs text-muted">
            {t("Vous validerez le paiement sur l'interface sécurisée de votre opérateur (Orange Money, Moov, Wave, MTN...).")}
          </p>
        </div>
      </form>
    </section>
  );
}
