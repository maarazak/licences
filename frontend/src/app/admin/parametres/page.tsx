import type { Metadata } from "next";
import { ActionForm, SubmitButton } from "@/components/forms";
import { PageHeader } from "@/components/ui";
import { updateSettingsAction } from "@/server/actions/admin";
import { env } from "@/server/env";
import { getSettings } from "@/server/services/settings";
import { requireAdmin } from "@/server/auth/session";

export const metadata: Metadata = { title: "Paramètres" };

export default async function AdminSettingsPage() {
  // Le layout ne suffit pas (il ne se ré-exécute pas à chaque navigation) : chaque page vérifie le droit elle-même.
  await requireAdmin("/admin");
  const settings = await getSettings();
  const e = env();
  const checks: Array<[string, boolean]> = [
    ["Clé API fournisseur (SUPPLIER_API_KEY)", !!e.SUPPLIER_API_KEY],
    ["Jeton API pawaPay (PAWAPAY_API_TOKEN)", !!e.PAWAPAY_API_TOKEN],
    ["Envoi d'e-mails (SMTP_HOST)", !!e.SMTP_HOST],
    ["Adresse d'assistance (SUPPORT_EMAIL)", !!e.SUPPORT_EMAIL],
    ["Site en HTTPS (APP_URL)", e.APP_URL.startsWith("https://")],
  ];

  return (
    <div className="max-w-2xl space-y-6">
      <PageHeader title="Paramètres" />

      <ActionForm action={updateSettingsAction} className="card space-y-5 p-5">
        <h2 className="h3">Prix</h2>
        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label htmlFor="usdRate" className="label">
              Taux du dollar : 1 $ en FCFA
            </label>
            <input id="usdRate" name="usdRate" inputMode="numeric" required defaultValue={settings.usdRate} className="input" />
            <p className="hint">Un seul taux pour le coût d&apos;achat, les prix de vente, le blocage des ventes à perte et l&apos;affichage en dollars sur le site (« Taux du jour »).</p>
          </div>
          <div>
            <label htmlFor="marginPercent" className="label">
              Marge générale (%)
            </label>
            <input id="marginPercent" name="marginPercent" inputMode="numeric" required defaultValue={settings.marginPercent} className="input" />
          </div>
          <div>
            <label htmlFor="minMarginXof" className="label">
              Marge minimale par vente (FCFA)
            </label>
            <input id="minMarginXof" name="minMarginXof" inputMode="numeric" required defaultValue={settings.minMarginXof} className="input" />
          </div>
          <div>
            <label htmlFor="roundingStep" className="label">
              Arrondi (FCFA)
            </label>
            <input id="roundingStep" name="roundingStep" inputMode="numeric" required defaultValue={settings.roundingStep} className="input" />
          </div>
        </div>
        <p className="hint">Prix de vente = le plus élevé de « coût en $ × taux × (1 + marge) » et « coût + marge minimale », arrondi au multiple supérieur (250 FCFA donne des prix ronds : 1 750, 2 000, 2 250…). La marge minimale garantit un gain par vente même sur les produits très bon marché. Prévoyez dans la marge les frais du prestataire de paiement et le coût de recharge du solde fournisseur. Un produit peut avoir son propre prix fixe (prioritaire) ou sa propre marge en %.</p>

        <h2 className="h3 border-t border-line pt-5">Boutique</h2>
        <label className="flex cursor-pointer items-center gap-2 text-sm font-medium">
          <input type="checkbox" name="autoPublish" defaultChecked={settings.autoPublish} className="size-4 accent-brand" />
          Publier automatiquement les nouveaux produits du fournisseur
        </label>
        <div>
          <label htmlFor="notice" className="label">
            Bandeau d&apos;information
          </label>
          <input id="notice" name="notice" maxLength={300} defaultValue={settings.notice} className="input" />
          <p className="hint">Affiché en haut de toutes les pages. Vide = masqué.</p>
        </div>
        <SubmitButton className="btn btn-brand">Enregistrer</SubmitButton>
      </ActionForm>

      <section className="card p-5">
        <h2 className="h3">Configuration du serveur</h2>
        <p className="hint mb-3">Ces réglages se trouvent dans le fichier .env. Les valeurs ne sont jamais affichées.</p>
        <ul className="space-y-1.5 text-sm">
          {checks.map(([label, ok]) => (
            <li key={label} className="flex items-center justify-between gap-4">
              <span>{label}</span>
              <span className={`badge ${ok ? "badge-success" : "badge-danger"}`}>{ok ? "Configuré" : "Manquant"}</span>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
