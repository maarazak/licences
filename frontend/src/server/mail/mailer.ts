import "server-only";
import nodemailer, { type Transporter } from "nodemailer";
import { env, isProd } from "@/server/env";

let transporter: Transporter | null = null;

function transport(): Transporter | null {
  const e = env();
  if (!e.SMTP_HOST) return null;
  if (!transporter) {
    transporter = nodemailer.createTransport({
      host: e.SMTP_HOST,
      port: e.SMTP_PORT,
      secure: e.SMTP_PORT === 465,
      requireTLS: e.SMTP_PORT !== 465,
      auth: e.SMTP_USER ? { user: e.SMTP_USER, pass: e.SMTP_PASSWORD } : undefined,
      // Un relais SMTP muet ne doit jamais bloquer une action ou la tâche planifiée.
      connectionTimeout: 10_000,
      greetingTimeout: 10_000,
      socketTimeout: 20_000,
    });
  }
  return transporter;
}

export type Mail = { to: string; subject: string; text: string; html: string };

/**
 * Envoie un e-mail. Sans SMTP configuré (développement), le contenu texte est
 * écrit dans la console pour pouvoir récupérer le code de connexion.
 * Retourne false en cas d'échec : l'appelant décide si c'est bloquant.
 */
export async function sendMail(mail: Mail): Promise<boolean> {
  const t = transport();
  if (!t) {
    if (isProd()) {
      console.error("[mail] SMTP non configuré : e-mail non envoyé", mail.subject);
      return false;
    }
    console.info(`\n──── E-mail (non envoyé, SMTP absent) ────\nÀ : ${mail.to}\nObjet : ${mail.subject}\n\n${mail.text}\n──────────────────────────────────────────\n`);
    return true;
  }
  try {
    await t.sendMail({ from: env().MAIL_FROM, to: mail.to, subject: mail.subject, text: mail.text, html: mail.html });
    return true;
  } catch (error) {
    console.error("[mail] échec d'envoi", mail.subject, error instanceof Error ? error.message : error);
    return false;
  }
}
