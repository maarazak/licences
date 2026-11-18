import { deliveryKindLabel } from "@/Types/enums";
import { formatWarranty } from "./format";
import type { TFn } from "./i18n";
import { SITE_NAME } from "./site";

type TermsInput = { title: string; accessKind: string; duration: string; warranty: string };

/** Conditions propres à une offre : affichées sur la fiche et à valider avant le paiement. */
export function offerTerms(p: TermsInput, t: TFn): string[] {
  const access = t(deliveryKindLabel(p.accessKind)).toLowerCase();
  const warranty = p.warranty.trim() ? t("Garantie : {value}.", { value: t(formatWarranty(p.warranty)).toLowerCase() }) : t("Garantie : aucune garantie annoncée par le fournisseur.");
  return [
    t("Ce que vous recevez : {access} pour {title}, disponible dans votre espace {name}.", { access, title: p.title, name: SITE_NAME }),
    t("Durée : {value}.", { value: p.duration.toLowerCase() }),
    warranty,
    t("Livraison : automatique après confirmation du paiement, en général en quelques minutes ; certaines offres demandent une activation manuelle plus longue."),
    t("Un accès qui ne fonctionne pas doit être signalé depuis votre commande pendant la durée de garantie : il est remplacé ou, si c'est impossible, son montant est crédité sur votre solde."),
    t("{name} est une boutique indépendante, non affiliée à l'éditeur du service.", { name: SITE_NAME }),
  ];
}
