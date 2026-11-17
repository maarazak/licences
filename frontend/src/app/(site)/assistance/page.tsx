import { ChevronDown, Mail } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { currentUser } from "@/server/auth/session";
import { env } from "@/server/env";
import { getI18n } from "@/server/i18n";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Assistance") };
}

export default async function SupportPage() {
  const [user, { t }] = await Promise.all([currentUser(), getI18n()]);
  const supportEmail = env().SUPPORT_EMAIL;
  const faq = [
    [t("Je n'ai pas reçu mon code de connexion"), t("Vérifiez vos courriers indésirables, puis demandez un nouveau code depuis la page de connexion. Le dernier code envoyé est le seul valable.")],
    [t("Mon paiement est toujours en attente"), t("La commande démarre quand le prestataire confirme le paiement. Cela peut prendre quelques minutes selon l'opérateur. Depuis la commande, vous pouvez reprendre un paiement non terminé.")],
    [t("Mon compte a été débité mais la commande n'apparaît pas payée"), t("Ne payez pas une seconde fois. Vérifiez d'abord votre solde mobile money, puis ouvrez une demande d'assistance depuis la commande avec sa référence.")],
    [t("Où trouver mes accès ?"), t("Dans Mes accès et dans le détail de chaque commande, après connexion. Pour votre sécurité, les accès ne sont pas envoyés par e-mail.")],
    [t("Quelle garantie sur mon achat ?"), t("Elle dépend de chaque offre : elle est indiquée sur la fiche et rappelée avant le paiement. Un accès défaillant doit être signalé depuis la commande pendant la garantie.")],
    [t("Une commande payée n'a pas pu être livrée"), t("Vous choisissez depuis la commande entre un avoir immédiat sur votre solde et un remboursement sur votre moyen de paiement.")],
  ];
  return (
    <div className="container-page max-w-3xl py-10">
      <h1 className="h1">{t("Assistance")}</h1>
      <p className="lead mt-3">{t("Chaque demande est rattachée à votre compte et à votre commande, pour être suivie jusqu'au bout.")}</p>

      <div className="card mt-6 flex flex-wrap items-center justify-between gap-4 p-5">
        <div>
          <h2 className="h3">{t("Ouvrir une demande")}</h2>
          <p className="mt-1 text-sm text-muted">{t("N'indiquez jamais de mot de passe ni de code dans un message.")}</p>
        </div>
        <Link href={user ? "/compte/assistance/nouveau" : "/connexion?next=%2Fcompte%2Fassistance%2Fnouveau"} className="btn btn-brand">
          {user ? t("Nouvelle demande") : t("Se connecter pour faire une demande")}
        </Link>
      </div>

      <h2 className="h2 mt-10">{t("Questions fréquentes")}</h2>
      <div className="mt-4 space-y-2.5">
        {faq.map(([question, answer]) => (
          <details key={question} className="card group px-5 py-4">
            <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-bold">
              {question}
              <ChevronDown className="size-4 shrink-0 text-muted transition-transform group-open:rotate-180" aria-hidden="true" />
            </summary>
            <p className="mt-2.5 text-sm leading-relaxed text-muted">{answer}</p>
          </details>
        ))}
      </div>

      {supportEmail ? (
        <p className="mt-8 flex items-center gap-2 text-sm text-muted">
          <Mail className="size-4" aria-hidden="true" />
          {t("Vous n'arrivez pas à vous connecter ? Écrivez à")}{" "}
          <a href={`mailto:${supportEmail}`} className="link">
            {supportEmail}
          </a>
        </p>
      ) : null}
    </div>
  );
}
