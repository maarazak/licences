import "server-only";
import { formatXof } from "@/lib/format";
import type { Lang, TFn } from "@/lib/i18n";
import { SITE_NAME } from "@/lib/site";
import { appUrl } from "@/server/env";
import { translatorFor } from "@/server/i18n";
import type { Mail } from "./mailer";

function esc(value: string): string {
  return value.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]!);
}

type Block = { title: string; lines: string[]; cta?: { label: string; url: string }; footer?: string };

function build(to: string, subject: string, block: Block, t: TFn): Mail {
  const footer = block.footer ?? t("Cet e-mail vous est envoyé par {name}. Ne transmettez jamais vos codes ni vos mots de passe.", { name: SITE_NAME });
  const text = [block.title, "", ...block.lines, ...(block.cta ? ["", `${block.cta.label} : ${block.cta.url}`] : []), "", footer].join("\n");
  const html = `<!doctype html><html><body style="margin:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#0b1220">
<div style="max-width:520px;margin:0 auto;padding:24px 16px">
<div style="background:#ffffff;border-radius:16px;padding:28px 24px;border:1px solid #e6e9f1">
<p style="margin:0 0 20px;font-size:16px;font-weight:bold;color:#0d5c4a">${esc(SITE_NAME)}</p>
<h1 style="margin:0 0 16px;font-size:20px;line-height:1.3">${esc(block.title)}</h1>
${block.lines.map((l) => `<p style="margin:0 0 12px;font-size:15px;line-height:1.55">${esc(l)}</p>`).join("")}
${block.cta ? `<p style="margin:24px 0 4px"><a href="${esc(block.cta.url)}" style="display:inline-block;background:#ff6a13;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:12px;font-size:15px;font-weight:bold">${esc(block.cta.label)}</a></p>` : ""}
</div>
<p style="margin:16px 4px 0;font-size:12px;line-height:1.5;color:#5b6475">${esc(footer)}</p>
</div></body></html>`;
  return { to, subject, text, html };
}

export function loginCodeMail(to: string, code: string, lang: Lang = "fr"): Mail {
  const t = translatorFor(lang);
  return build(
    to,
    t("{code} est votre code de connexion {name}", { code, name: SITE_NAME }),
    { title: t("Votre code de connexion"), lines: [t("Code : {code}", { code }), t("Il est valable 10 minutes et ne peut servir qu'une fois."), t("Si vous n'avez pas demandé ce code, ignorez cet e-mail : personne ne peut se connecter sans lui.")] },
    t,
  );
}

/** Code de double authentification du backoffice (en français : le backoffice n'existe qu'en français). */
export function adminCodeMail(to: string, code: string): Mail {
  const t = translatorFor("fr");
  return build(
    to,
    `${code} est votre code de vérification administrateur ${SITE_NAME}`,
    {
      title: "Vérification de connexion administrateur",
      lines: [`Code : ${code}`, "Il est valable 10 minutes et ne peut servir qu'une fois.", "Si vous n'êtes pas à l'origine de cette connexion, ne communiquez ce code à personne et changez votre mot de passe sans attendre."],
      footer: `Cet e-mail vous est envoyé par ${SITE_NAME}. Ne transmettez jamais vos codes ni vos mots de passe.`,
    },
    t,
  );
}

type OrderInfo = { id: string; reference: string; productName: string; quantity: number; totalXof: number };

const orderUrl = (o: OrderInfo) => appUrl(`/compte/achats/${o.id}`);
const orderLine = (o: OrderInfo, t: TFn) => t("Commande {reference} — {product} × {quantity} — {total}", { reference: o.reference, product: o.productName, quantity: o.quantity, total: formatXof(o.totalXof) });

export function orderPaidMail(to: string, o: OrderInfo, lang: Lang = "fr"): Mail {
  const t = translatorFor(lang);
  return build(to, t("Paiement reçu — commande {reference}", { reference: o.reference }), { title: t("Nous avons bien reçu votre paiement"), lines: [orderLine(o, t), t("Nous préparons vos accès. Vous recevrez un e-mail dès qu'ils sont disponibles.")], cta: { label: t("Suivre ma commande"), url: orderUrl(o) } }, t);
}

export function orderDeliveredMail(to: string, o: OrderInfo, lang: Lang = "fr"): Mail {
  const t = translatorFor(lang);
  return build(to, t("Votre accès est disponible — commande {reference}", { reference: o.reference }), { title: t("Votre accès est disponible"), lines: [orderLine(o, t), t("Pour votre sécurité, les accès ne sont pas envoyés par e-mail : ils vous attendent dans votre espace « Mes achats ».")], cta: { label: t("Voir mes accès"), url: orderUrl(o) } }, t);
}

export function orderActivationMail(to: string, o: OrderInfo, lang: Lang = "fr"): Mail {
  const t = translatorFor(lang);
  return build(to, t("Activation en cours — commande {reference}", { reference: o.reference }), { title: t("Votre activation est en cours"), lines: [orderLine(o, t), t("Cette offre est activée manuellement sur l'adresse e-mail que vous avez indiquée. Vous recevrez un e-mail dès que c'est fait.")], cta: { label: t("Suivre ma commande"), url: orderUrl(o) } }, t);
}

export function orderDelayedMail(to: string, o: OrderInfo, lang: Lang = "fr"): Mail {
  const t = translatorFor(lang);
  return build(
    to,
    t("Votre commande {reference} est prise en charge", { reference: o.reference }),
    { title: t("Votre commande demande une intervention de notre équipe"), lines: [orderLine(o, t), t("Votre paiement est bien enregistré, mais la livraison automatique n'a pas abouti. Notre équipe s'en occupe : vous serez livré ou remboursé."), t("Inutile de payer à nouveau.")], cta: { label: t("Suivre ma commande"), url: orderUrl(o) } },
    t,
  );
}

export function orderRefundedMail(to: string, o: OrderInfo, lang: Lang = "fr"): Mail {
  const t = translatorFor(lang);
  return build(to, t("Commande {reference} remboursée", { reference: o.reference }), { title: t("Votre commande a été remboursée"), lines: [orderLine(o, t), t("Le remboursement a été émis vers votre moyen de paiement. Le délai d'arrivée dépend de votre opérateur.")], cta: { label: t("Voir ma commande"), url: orderUrl(o) } }, t);
}

/**
 * Relance d'un paiement non terminé.
 * Étape 1 : la session de paiement est encore ouverte. Étape 2 : elle a expiré, on propose de recommencer.
 */
export function paymentReminderMail(to: string, o: OrderInfo & { slug: string }, stage: 1 | 2, lang: Lang = "fr"): Mail {
  const t = translatorFor(lang);
  const footer = t("Vous recevez ce rappel parce que vous avez commencé une commande sur {name}. Pour ne plus en recevoir, désactivez-les dans votre Profil : {url}", { name: SITE_NAME, url: appUrl("/compte/profil") });
  if (stage === 1) {
    return build(
      to,
      t("Votre paiement n'est pas terminé — {product}", { product: o.productName }),
      {
        title: t("Votre paiement n'est pas encore confirmé"),
        lines: [orderLine(o, t), t("Si vous avez déjà payé, patientez quelques minutes : la confirmation peut prendre un peu de temps et ne demande aucune action. Ne payez pas une seconde fois."), t("Sinon, vous pouvez reprendre votre paiement tant que la session est ouverte (environ une heure après la commande).")],
        cta: { label: t("Reprendre mon paiement"), url: orderUrl(o) },
        footer,
      },
      t,
    );
  }
  return build(
    to,
    t("{product} vous attend toujours", { product: o.productName }),
    {
      title: t("Votre commande n'a pas été finalisée"),
      lines: [orderLine(o, t), t("La session de paiement a expiré et aucun montant n'a été débité. L'offre est toujours disponible : vous pouvez recommencer en quelques instants.")],
      cta: { label: t("Recommencer ma commande"), url: appUrl(`/commande/validation?produit=${encodeURIComponent(o.slug)}`) },
      footer,
    },
    t,
  );
}

export function orderCreditedMail(to: string, o: OrderInfo, lang: Lang = "fr"): Mail {
  const t = translatorFor(lang);
  return build(
    to,
    t("Commande {reference} : avoir crédité", { reference: o.reference }),
    { title: t("Le montant de votre commande est crédité sur votre solde"), lines: [orderLine(o, t), t("{amount} sont disponibles sur votre solde. Vous pouvez les utiliser pour payer une prochaine commande.", { amount: formatXof(o.totalXof) })], cta: { label: t("Voir mon solde"), url: appUrl("/compte/solde") } },
    t,
  );
}

export function stockBackMail(to: string, p: { slug: string; name: string }, lang: Lang = "fr"): Mail {
  const t = translatorFor(lang);
  return build(to, t("{name} est de nouveau disponible", { name: p.name }), { title: t("L'offre que vous attendiez est de retour"), lines: [t("{name} est de nouveau en stock.", { name: p.name }), t("Vous ne recevrez pas d'autre e-mail pour cette alerte.")], cta: { label: t("Voir l'offre"), url: appUrl(`/boutique/${p.slug}`) } }, t);
}

export function ticketReplyMail(to: string, tk: { id: string; reference: string; subject: string }, lang: Lang = "fr"): Mail {
  const t = translatorFor(lang);
  return build(to, t("Réponse à votre demande {reference}", { reference: tk.reference }), { title: t("Nous avons répondu à votre demande"), lines: [t("Demande {reference} — {subject}", { reference: tk.reference, subject: tk.subject }), t("Connectez-vous pour lire la réponse et y répondre.")], cta: { label: t("Lire la réponse"), url: appUrl(`/compte/assistance/${tk.id}`) } }, t);
}

/** Alerte interne pour les administrateurs : toujours en français. */
export function adminAlertMail(to: string, subject: string, lines: string[], path: string): Mail {
  const t = translatorFor("fr");
  return build(to, `[${SITE_NAME}] ${subject}`, { title: subject, lines, cta: { label: "Ouvrir le backoffice", url: appUrl(path) }, footer: "Alerte interne destinée aux administrateurs." }, t);
}
