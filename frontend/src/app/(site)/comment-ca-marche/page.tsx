import { ArrowRight, Package, ShoppingBag, Smartphone } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { getI18n } from "@/server/i18n";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Comment ça marche") };
}

export default async function HowItWorksPage() {
  const { t } = await getI18n();
  const steps = [
    { Icon: ShoppingBag, title: t("Choisissez votre offre"), text: t("Type d'accès (lien, code ou compte), durée, garantie et disponibilité sont affichés avant de payer.") },
    { Icon: Smartphone, title: t("Payez en FCFA"), text: t("Connectez-vous avec un code reçu par e-mail, puis payez par Mobile Money sur la page sécurisée du prestataire. La commande démarre quand le paiement est confirmé.") },
    { Icon: Package, title: t("Recevez votre accès"), text: t("Vos accès apparaissent dans Mes achats et un e-mail vous prévient. Si l'activation prend du temps, la commande affiche son état.") },
  ];
  return (
    <div className="container-page max-w-3xl py-10">
      <h1 className="h1">{t("Comment ça marche")}</h1>
      <p className="lead mt-3">{t("Trois étapes, quelques minutes.")}</p>
      <ol className="mt-8 space-y-3">
        {steps.map(({ Icon, title, text }, index) => (
          <li key={title} className="card flex gap-4 p-5">
            <span className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-bright to-brand text-white shadow-[0_14px_28px_-10px_rgb(13_92_74/0.7)]">
              <Icon className="size-6" aria-hidden="true" />
            </span>
            <div>
              <p className="text-xs font-bold text-accent-ink">{t("Étape {n}", { n: index + 1 })}</p>
              <h2 className="h3 mt-0.5 text-lg">{title}</h2>
              <p className="mt-1.5 text-sm leading-relaxed text-muted">{text}</p>
            </div>
          </li>
        ))}
      </ol>
      <div className="card mt-6 p-5">
        <h2 className="h3">{t("Et si la livraison échoue ?")}</h2>
        <p className="mt-1.5 text-sm leading-relaxed text-muted">{t("Vous êtes prévenu par e-mail et vous choisissez, depuis votre commande, entre un remboursement et un avoir sur votre solde, utilisable pour une prochaine commande.")}</p>
      </div>
      <Link href="/boutique" className="btn btn-primary mt-8">
        {t("Voir les offres")}
        <ArrowRight className="size-4" aria-hidden="true" />
      </Link>
    </div>
  );
}
