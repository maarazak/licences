"use client";

import { ArrowRight, Mail } from "lucide-react";
import { useActionState, useState } from "react";
import { INITIAL_STATE, type ActionState } from "@/lib/action-state";
import { requestCodeAction, verifyCodeAction } from "@/server/actions/auth";
import { FormMessage, SubmitButton } from "./forms";
import { useI18n } from "./i18n";

/** Connexion en deux temps : adresse e-mail, puis code à 6 chiffres. */
export function LoginForm({ next }: { next: string }) {
  const { t } = useI18n();
  // Adresse à laquelle un code vient d'être envoyé ; null = étape « e-mail ».
  const [email, setEmail] = useState<string | null>(null);
  // Message reporté sur l'étape « e-mail » quand un code a expiré.
  const [carried, setCarried] = useState<string | undefined>(undefined);

  const [requestState, requestAction] = useActionState(async (prev: ActionState, formData: FormData) => {
    const result = await requestCodeAction(prev, formData);
    if (result.step === "code" && result.email) {
      setCarried(undefined);
      setEmail(result.email);
    }
    return result;
  }, INITIAL_STATE);

  const [verifyState, verifyAction] = useActionState(async (prev: ActionState, formData: FormData) => {
    const result = await verifyCodeAction(prev, formData);
    // Code expiré ou verrouillé : retour à la première étape pour en demander un autre.
    if (result.error && result.step !== "code") {
      setCarried(result.error);
      setEmail(null);
      return INITIAL_STATE;
    }
    return result;
  }, INITIAL_STATE);

  if (email) {
    return (
      <form action={verifyAction} className="space-y-4">
        <input type="hidden" name="email" value={email} />
        <input type="hidden" name="next" value={next} />
        <p className="alert alert-info flex gap-3">
          <Mail className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
          <span>
            {t("Un code à 6 chiffres a été envoyé à")} <strong className="[overflow-wrap:anywhere]">{email}</strong>. {t("Pensez à vérifier vos courriers indésirables.")}
          </span>
        </p>
        <div>
          <label htmlFor="code" className="label">
            {t("Code reçu par e-mail")}
          </label>
          <input
            id="code"
            name="code"
            inputMode="numeric"
            autoComplete="one-time-code"
            pattern="[0-9]{6}"
            maxLength={6}
            required
            autoFocus
            placeholder="000000"
            onChange={(e) => {
              e.currentTarget.value = e.currentTarget.value.replace(/\D/g, "").slice(0, 6);
            }}
            className="input text-center font-display text-xl tracking-[0.4em]"
          />
        </div>
        <FormMessage state={verifyState} />
        <SubmitButton className="btn btn-primary btn-block min-h-12">
          {t("Se connecter")}
          <ArrowRight className="size-4" aria-hidden="true" />
        </SubmitButton>
        <button type="button" className="btn btn-soft btn-block" onClick={() => setEmail(null)}>
          {t("Utiliser une autre adresse")}
        </button>
      </form>
    );
  }

  return (
    <form action={requestAction} className="space-y-4">
      <div>
        <label htmlFor="email" className="label">
          {t("Adresse e-mail")}
        </label>
        <input id="email" name="email" type="email" autoComplete="email" required maxLength={191} className="input" />
        <p className="hint">{t("Pas de mot de passe. Le code est valable 10 minutes.")}</p>
      </div>
      <FormMessage state={carried ? { error: carried } : requestState.step === "code" ? INITIAL_STATE : requestState} />
      <SubmitButton className="btn btn-primary btn-block min-h-12">
        {t("Recevoir un code")}
        <ArrowRight className="size-4" aria-hidden="true" />
      </SubmitButton>
    </form>
  );
}
