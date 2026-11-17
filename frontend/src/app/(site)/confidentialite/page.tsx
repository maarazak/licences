import type { Metadata } from "next";
import { LegalPage } from "@/components/legal";
import { SITE_NAME } from "@/lib/site";
import { getI18n } from "@/server/i18n";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Confidentialité") };
}

/** MODÈLE À FAIRE RELIRE avant l'ouverture au public (voir aussi les mentions légales). */
export default async function PrivacyPage() {
  const { t } = await getI18n();
  return (
    <LegalPage
      title={t("Politique de confidentialité")}
      updatedLabel={t("Dernière mise à jour")}
      updated={t("12 novembre 2026")}
      sections={[
        { title: t("Données collectées"), paragraphs: [t("Votre adresse e-mail (connexion, reçus, suivi), vos prénom, nom, téléphone et pays (transmis au prestataire de paiement pour l'achat), vos commandes et vos échanges avec l'assistance."), t("Pour les offres activées sur une adresse e-mail, l'adresse à activer est transmise à notre fournisseur dans le seul but d'effectuer l'activation.")] },
        { title: t("Ce que nous ne conservons pas"), paragraphs: [t("{name} ne voit ni ne conserve vos numéros de carte ou codes mobile money : le paiement se déroule entièrement chez le prestataire de paiement. Le site n'utilise pas de mot de passe.", { name: SITE_NAME })] },
        { title: t("Sécurité"), paragraphs: [t("Les accès livrés sont chiffrés dans notre base de données et ne sont affichés qu'au propriétaire connecté de la commande. Les codes de connexion et les sessions sont stockés sous forme d'empreintes.")] },
        { title: t("Cookies"), paragraphs: [t("Le site utilise uniquement des cookies nécessaires à son fonctionnement : un cookie de session pour rester connecté et un cookie de préférence pour la langue. Aucun cookie publicitaire ni de suivi.")] },
        { title: t("Durée de conservation"), paragraphs: [t("Les commandes et leurs justificatifs sont conservés pendant la durée exigée par les obligations comptables. Les codes de connexion sont supprimés au plus tard 24 heures après leur expiration.")] },
        { title: t("Vos droits"), paragraphs: [t("Vous pouvez demander l'accès à vos données, leur rectification ou la suppression de votre compte en ouvrant une demande depuis la rubrique Assistance.")] },
      ]}
    />
  );
}
