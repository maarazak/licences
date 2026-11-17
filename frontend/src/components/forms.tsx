"use client";

import { Check, Copy } from "lucide-react";
import { useActionState, useEffect, useRef, useState } from "react";
import { useFormStatus } from "react-dom";
import { INITIAL_STATE, type ActionState } from "@/lib/action-state";
import { confirmDialog, hideBusy, notify, showBusy } from "./dialogs";
import { useI18n } from "./i18n";

type Action = (prev: ActionState, formData: FormData) => Promise<ActionState>;

/**
 * React 19 remet un formulaire à zéro quand son action se termine, même sur une erreur : le client perdrait sa
 * saisie. Ce hook mémorise les valeurs envoyées et les rétablit quand l'action renvoie une erreur.
 */
export function useKeepOnError(action: Action) {
  const [state, dispatch] = useActionState(
    async (prev: ActionState, formData: FormData) => {
      saved.current = formData;
      return action(prev, formData);
    },
    INITIAL_STATE,
  );
  const saved = useRef<FormData | null>(null);
  const ref = useRef<HTMLFormElement>(null);
  useEffect(() => {
    const form = ref.current;
    const data = saved.current;
    if (!state.error || !form || !data) return;
    const seen: Record<string, number> = {};
    for (const el of Array.from(form.elements)) {
      if (!(el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement || el instanceof HTMLSelectElement) || !el.name || el.type === "hidden" || el.type === "password") continue;
      const values = data.getAll(el.name).map(String);
      if (el instanceof HTMLInputElement && (el.type === "checkbox" || el.type === "radio")) el.checked = values.includes(el.value);
      else {
        // Champs répétés (adresses d'activation) : restitués par rang. Les champs vides n'ont pas été envoyés.
        const i = (seen[el.name] = (seen[el.name] ?? -1) + 1);
        el.value = values[i] ?? values[0] ?? "";
      }
    }
  }, [state]);
  return [state, dispatch, ref] as const;
}

/** Cercle de chargement. Hérite de la couleur du texte. */
export function Spinner({ className = "" }: { className?: string }) {
  return <span aria-hidden="true" className={`spinner ${className}`.trim()} />;
}

export function SubmitButton({ children, className = "btn btn-primary", pendingLabel, disabled }: { children: React.ReactNode; className?: string; pendingLabel?: string; disabled?: boolean }) {
  const { pending } = useFormStatus();
  const { t } = useI18n();
  return (
    <button type="submit" className={className} disabled={pending || disabled} aria-busy={pending}>
      {pending ? (
        <>
          <Spinner />
          {pendingLabel ?? t("Un instant…")}
        </>
      ) : (
        children
      )}
    </button>
  );
}

/** À placer dans un formulaire : affiche une boîte « traitement en cours » tant que l'action s'exécute. */
function BusyDialog({ message }: { message: string }) {
  const { pending } = useFormStatus();
  const { t } = useI18n();
  useEffect(() => {
    if (!pending) return;
    showBusy(t(message));
    return hideBusy;
  }, [pending, message, t]);
  return null;
}

/** Affiche le résultat d'une action sous forme de notification (SweetAlert2). */
function useNotifyResult(state: ActionState) {
  const { t } = useI18n();
  useEffect(() => {
    if (state.error) notify("error", t(state.error));
    else if (state.success) notify("success", t(state.success));
  }, [state, t]);
}

/** Message de retour d'une action. Le serveur renvoie du français : il est traduit ici selon la langue du visiteur. */
export function FormMessage({ state }: { state: ActionState }) {
  const { t } = useI18n();
  if (state.error)
    return (
      <p role="alert" className="alert alert-error">
        {t(state.error)}
      </p>
    );
  if (state.success)
    return (
      <p role="status" className="alert alert-success">
        {t(state.success)}
      </p>
    );
  return null;
}

/**
 * Formulaire branché sur une action serveur, avec affichage du résultat.
 * `resetOnSuccess` vide les champs après un succès (réponses, notes…).
 */
export function ActionForm({ action, children, className, resetOnSuccess = false }: { action: Action; children: React.ReactNode; className?: string; resetOnSuccess?: boolean }) {
  const [state, formAction, ref] = useKeepOnError(action);
  useNotifyResult(state);
  useEffect(() => {
    if (resetOnSuccess && state.success) ref.current?.reset();
  }, [state, resetOnSuccess, ref]);
  return (
    <form ref={ref} action={formAction} className={className}>
      {children}
    </form>
  );
}

/**
 * Bouton d'action unique (publier, relancer…). La confirmation et le résultat passent par SweetAlert2 ;
 * `danger` colore le bouton de confirmation en rouge pour les actions risquées ; `busy` affiche une boîte de
 * traitement (cercle) pendant les opérations longues ; le bouton, lui, montre toujours un cercle pendant l'envoi.
 */
export function ActionButton({ action, fields, children, className = "btn btn-soft btn-sm", confirm, danger = false, busy, pendingLabel }: { action: Action; fields: Record<string, string>; children: React.ReactNode; className?: string; confirm?: string; danger?: boolean; busy?: string; pendingLabel?: string }) {
  const [state, formAction] = useActionState(action, INITIAL_STATE);
  const { t } = useI18n();
  const confirmed = useRef(false);
  const asking = useRef(false);
  useNotifyResult(state);
  return (
    <form
      action={formAction}
      className="inline-flex"
      onSubmit={(event) => {
        if (!confirm) return;
        if (confirmed.current) {
          confirmed.current = false;
          return;
        }
        event.preventDefault();
        if (asking.current) return;
        asking.current = true;
        const form = event.currentTarget;
        void confirmDialog({ title: t("Confirmation"), text: t(confirm), confirmLabel: t("Confirmer"), cancelLabel: t("Annuler"), danger })
          .then((ok) => {
            if (!ok) return;
            confirmed.current = true;
            form.requestSubmit();
          })
          .finally(() => {
            asking.current = false;
          });
      }}
    >
      {Object.entries(fields).map(([name, value]) => (
        <input key={name} type="hidden" name={name} value={value} />
      ))}
      <SubmitButton className={className} pendingLabel={pendingLabel ?? busy}>
        {children}
      </SubmitButton>
      {busy ? <BusyDialog message={busy} /> : null}
    </form>
  );
}

/** Copie une valeur dans le presse-papiers (accès livrés). */
export function CopyButton({ value }: { value: string }) {
  const [copied, setCopied] = useState(false);
  const { t } = useI18n();
  return (
    <button
      type="button"
      className="btn btn-soft btn-sm shrink-0"
      onClick={async () => {
        try {
          await navigator.clipboard.writeText(value);
          setCopied(true);
          setTimeout(() => setCopied(false), 2000);
        } catch {
          setCopied(false);
        }
      }}
    >
      {copied ? <Check className="size-4" aria-hidden="true" /> : <Copy className="size-4" aria-hidden="true" />}
      {copied ? t("Copié") : t("Copier")}
    </button>
  );
}

/** Menu déroulant qui soumet son formulaire dès qu'on change la valeur. */
export function AutoSubmitSelect({ name, defaultValue, options, label, className = "input" }: { name: string; defaultValue: string; options: Array<{ value: string; label: string }>; label: string; className?: string }) {
  return (
    <select name={name} defaultValue={defaultValue} aria-label={label} className={className} onChange={(event) => event.currentTarget.form?.requestSubmit()}>
      {options.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  );
}
