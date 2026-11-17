"use client";

import { ArrowRight, Eye, EyeOff, MailCheck } from "lucide-react";
import { useActionState, useState } from "react";
import { INITIAL_STATE, type ActionState } from "@/lib/action-state";
import { adminLoginAction, adminVerifyCodeAction } from "@/server/actions/auth";
import { FormMessage, SubmitButton } from "./forms";

/** Connexion du backoffice (en français uniquement) : e-mail + mot de passe, puis code reçu par e-mail (double authentification). */
export function AdminLoginForm() {
  const [show, setShow] = useState(false);
  // Adresse à laquelle un code vient d'être envoyé ; null = étape « mot de passe ».
  const [codeFor, setCodeFor] = useState<string | null>(null);
  // Message reporté sur la première étape quand le code a expiré ou que les essais sont épuisés.
  const [carried, setCarried] = useState<string | undefined>(undefined);

  const [loginState, loginAction] = useActionState(async (prev: ActionState, formData: FormData) => {
    const result = await adminLoginAction(prev, formData);
    if (result.step === "code" && result.email) {
      setCarried(undefined);
      setCodeFor(result.email);
    }
    return result;
  }, INITIAL_STATE);

  const [verifyState, verifyAction] = useActionState(async (prev: ActionState, formData: FormData) => {
    const result = await adminVerifyCodeAction(prev, formData);
    if (result.error && result.step !== "code") {
      setCarried(result.error);
      setCodeFor(null);
      return INITIAL_STATE;
    }
    return result;
  }, INITIAL_STATE);

  if (codeFor) {
    return (
      <form action={verifyAction} className="space-y-4">
        <input type="hidden" name="email" value={codeFor} />
        <p className="flex items-start gap-2.5 rounded-xl bg-brand-soft px-3.5 py-3 text-sm leading-relaxed text-brand-strong">
          <MailCheck className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
          <span>
            Mot de passe correct. Un code à 6 chiffres vient d&apos;être envoyé à <strong className="[overflow-wrap:anywhere]">{codeFor}</strong>.
          </span>
        </p>
        <div>
          <label htmlFor="admin-code" className="label">
            Code de vérification
          </label>
          <input
            id="admin-code"
            name="code"
            inputMode="numeric"
            pattern="[0-9]{6}"
            maxLength={6}
            autoComplete="one-time-code"
            autoFocus
            required
            placeholder="000000"
            onChange={(event) => {
              event.target.value = event.target.value.replace(/\D/g, "").slice(0, 6);
            }}
            className="input text-center font-display text-2xl tracking-[0.4em]"
          />
          <p className="hint">Valable 10 minutes, utilisable une seule fois.</p>
        </div>
        <FormMessage state={verifyState} />
        <SubmitButton className="btn btn-primary btn-block min-h-12">
          Valider
          <ArrowRight className="size-4" aria-hidden="true" />
        </SubmitButton>
        <button type="button" onClick={() => setCodeFor(null)} className="link block w-full text-center text-sm">
          Recommencer la connexion
        </button>
      </form>
    );
  }

  return (
    <form action={loginAction} className="space-y-4">
      <div>
        <label htmlFor="admin-email" className="label">
          Adresse e-mail
        </label>
        <input id="admin-email" name="email" type="email" autoComplete="username" required maxLength={191} className="input" />
      </div>
      <div>
        <label htmlFor="admin-password" className="label">
          Mot de passe
        </label>
        <div className="relative">
          <input id="admin-password" name="password" type={show ? "text" : "password"} autoComplete="current-password" required maxLength={200} className="input pr-12" />
          <button
            type="button"
            onClick={() => setShow((v) => !v)}
            aria-label={show ? "Masquer le mot de passe" : "Afficher le mot de passe"}
            aria-pressed={show}
            className="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-muted hover:text-ink"
          >
            {show ? <EyeOff className="size-4" aria-hidden="true" /> : <Eye className="size-4" aria-hidden="true" />}
          </button>
        </div>
      </div>
      {carried ? (
        <p role="alert" className="alert alert-error">
          {carried}
        </p>
      ) : (
        <FormMessage state={loginState} />
      )}
      <SubmitButton className="btn btn-primary btn-block min-h-12">
        Se connecter
        <ArrowRight className="size-4" aria-hidden="true" />
      </SubmitButton>
    </form>
  );
}
