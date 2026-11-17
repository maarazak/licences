import type { Metadata } from "next";
import { ActionForm, SubmitButton } from "@/components/forms";
import { CurrencySwitch, LanguageSwitch } from "@/components/site-chrome";
import { PageHeader } from "@/components/ui";
import { COUNTRIES } from "@/lib/enums";
import { logoutAction } from "@/server/actions/auth";
import { logoutEverywhereAction, setRemindersAction, updateProfileAction } from "@/server/actions/shop";
import { requireUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Profil"), robots: { index: false } };
}

export default async function SettingsPage() {
  const user = await requireUser("/compte/profil");
  const { t } = await getI18n();
  return (
    <div className="space-y-6">
      <PageHeader title={t("Profil")} />

      <section className="card p-5">
        <h2 className="h3 text-lg">{t("Identité")}</h2>
        <p className="mt-3 text-xs text-muted">{t("Adresse e-mail")}</p>
        <p className="text-sm font-semibold [overflow-wrap:anywhere]">{user.email}</p>
        <p className="hint">{t("Connexion par code e-mail, sans mot de passe.")}</p>
        <ActionForm action={updateProfileAction} className="mt-5 space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label htmlFor="firstName" className="label">
                {t("Prénom")}
              </label>
              <input id="firstName" name="firstName" defaultValue={user.firstName ?? ""} autoComplete="given-name" maxLength={100} className="input" />
            </div>
            <div>
              <label htmlFor="lastName" className="label">
                {t("Nom")}
              </label>
              <input id="lastName" name="lastName" defaultValue={user.lastName ?? ""} autoComplete="family-name" maxLength={100} className="input" />
            </div>
            <div>
              <label htmlFor="phone" className="label">
                {t("Téléphone")}
              </label>
              <input id="phone" name="phone" type="tel" defaultValue={user.phone ?? ""} autoComplete="tel" maxLength={20} className="input" />
            </div>
            <div>
              <label htmlFor="country" className="label">
                {t("Pays")}
              </label>
              <select id="country" name="country" defaultValue={user.country ?? ""} className="input">
                <option value="">{t("Non renseigné")}</option>
                {COUNTRIES.map((c) => (
                  <option key={c.code} value={c.code}>
                    {t(c.name)}
                  </option>
                ))}
              </select>
            </div>
          </div>
          <SubmitButton className="btn btn-brand">{t("Enregistrer")}</SubmitButton>
        </ActionForm>
      </section>

      <section className="card p-5">
        <h2 className="h3 text-lg">{t("Préférences")}</h2>
        <p className="mt-3 mb-2 text-sm font-semibold">{t("Langue")}</p>
        <LanguageSwitch />
        <p className="mt-5 mb-2 text-sm font-semibold">{t("Devise")}</p>
        <CurrencySwitch />
        <p className="hint">{t("Les prix peuvent s'afficher en FCFA ou en dollars (taux de change réel). Le paiement se fait toujours en FCFA.")}</p>
        <ActionForm action={setRemindersAction} className="mt-6 space-y-3 border-t border-line pt-5">
          <label className="flex cursor-pointer items-start gap-3">
            <input type="checkbox" name="mailReminders" defaultChecked={user.mailReminders} className="mt-1 size-4 shrink-0 accent-brand" />
            <span className="text-sm">
              <span className="font-semibold">{t("Rappels de paiement par e-mail")}</span>
              <span className="block text-xs leading-relaxed text-muted">{t("Un e-mail (deux au maximum) si vous démarrez une commande sans la payer. Les e-mails de confirmation de commande restent toujours envoyés.")}</span>
            </span>
          </label>
          <SubmitButton className="btn btn-soft btn-sm">{t("Enregistrer")}</SubmitButton>
        </ActionForm>
      </section>

      <section className="card p-5">
        <h2 className="h3 text-lg">{t("Session")}</h2>
        <div className="mt-3 flex flex-wrap gap-3">
          <form action={logoutAction}>
            <button type="submit" className="btn btn-soft">
              {t("Se déconnecter")}
            </button>
          </form>
          <form action={logoutEverywhereAction}>
            <button type="submit" className="btn btn-danger">
              {t("Déconnecter tous mes appareils")}
            </button>
          </form>
        </div>
      </section>
    </div>
  );
}
