import type { Metadata } from "next";
import { LegalPage } from "@/components/legal";
import { SITE_NAME } from "@/lib/site";
import { getI18n } from "@/server/i18n";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Conditions de vente") };
}

/**
 * MODÈLE À FAIRE RELIRE : ces conditions décrivent le fonctionnement réel du
 * site, mais doivent être validées par un juriste avant l'ouverture au public.
 */
export default async function TermsPage() {
  const { t } = await getI18n();
  return (
    <LegalPage
      title={t("Conditions générales de vente")}
      updatedLabel={t("Dernière mise à jour")}
      updated={t("12 novembre 2026")}
      sections={[
        { title: t("1. Objet"), paragraphs: [t("{name} vend des accès à des services numériques édités par des tiers : abonnements, licences, codes ou comptes. {name} est un revendeur indépendant, sans affiliation ni partenariat avec les éditeurs de ces services.", { name: SITE_NAME })] },
        { title: t("2. Compte client"), paragraphs: [t("L'achat nécessite un compte, créé automatiquement à la première connexion par code reçu par e-mail. Vous êtes responsable de l'accès à votre boîte e-mail. Un compte utilisé de manière frauduleuse peut être suspendu.")] },
        { title: t("3. Prix et paiement"), paragraphs: [t("Les prix sont indiqués en francs CFA et sont définitifs au moment de la validation de la commande. Le paiement s'effectue sur la page sécurisée de notre prestataire de paiement, ou avec le solde de votre compte s'il couvre la commande entière."), t("La commande n'est considérée comme payée qu'après confirmation du paiement par le prestataire à notre serveur.")] },
        { title: t("4. Livraison"), paragraphs: [t("Les accès sont mis à disposition dans votre espace client après confirmation du paiement, en général en quelques minutes. Certaines offres demandent une activation manuelle, signalée sur la fiche, dont le délai peut être plus long."), t("Les accès sont personnels. Les conditions d'utilisation de l'éditeur du service s'appliquent à leur usage.")] },
        { title: t("5. Garantie"), paragraphs: [t("La durée de garantie est indiquée sur chaque fiche et rappelée avant le paiement. Un accès qui ne fonctionne pas doit être signalé depuis la commande pendant cette durée. Il est alors remplacé ou, si le remplacement est impossible, son montant est crédité sur votre solde."), t("La garantie ne couvre pas un accès modifié, partagé ou utilisé en violation des consignes fournies.")] },
        { title: t("6. Commande non livrée"), paragraphs: [t("Si une commande payée ne peut pas être livrée, vous choisissez depuis la commande entre un avoir crédité immédiatement sur votre solde et un remboursement sur votre moyen de paiement. Le délai d'un remboursement dépend du prestataire et de votre opérateur.")] },
        { title: t("7. Rétractation"), paragraphs: [t("Les accès numériques sont livrés immédiatement après le paiement. Une fois l'accès livré, la commande ne peut plus être annulée, sous réserve de la garantie décrite ci-dessus.")] },
        { title: t("8. Solde"), paragraphs: [t("Le solde est un avoir utilisable uniquement sur le site, pour payer une commande entière. Il n'est ni transférable ni convertible en espèces, sauf disposition légale contraire.")] },
        { title: t("9. Assistance"), paragraphs: [t("Toute réclamation se fait depuis la rubrique Assistance de votre espace client, en la rattachant à la commande concernée.")] },
      ]}
    />
  );
}
