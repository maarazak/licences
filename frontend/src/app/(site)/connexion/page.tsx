import { ArrowLeft, KeyRound, ShieldCheck, Zap } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { redirect } from "next/navigation";
import { LoginForm } from "@/components/login-form";
import { SITE_NAME } from "@/lib/site";
import { currentUser } from "@/server/auth/session";
import { getI18n } from "@/server/i18n";
import { safeNextPath } from "@/server/security/request";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t("Connexion"), robots: { index: false } };
}

export default async function LoginPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const params = await searchParams;
  const next = safeNextPath(Array.isArray(params.next) ? params.next[0] : params.next);
  if (await currentUser()) redirect(next);
  const { t } = await getI18n();
  const points = [
    { Icon: KeyRound, text: t("Aucun mot de passe à retenir") },
    { Icon: Zap, text: t("Connexion en 30 secondes") },
    { Icon: ShieldCheck, text: t("Vos accès réservés à votre compte") },
  ];

  return (
    <div className="bg-hero relative overflow-hidden">
      <div aria-hidden="true" className="grid-lines absolute inset-0" />
      <div className="container-page relative flex justify-center py-12 sm:py-16">
        <div className="w-full max-w-md">
          <Link href="/boutique" className="inline-flex items-center gap-1.5 text-sm font-semibold text-muted hover:text-ink">
            <ArrowLeft className="size-4" aria-hidden="true" />
            {t("Retour à la boutique")}
          </Link>
          <div className="card mt-4 p-6 shadow-pop sm:p-8">
            <h1 className="h2">{t("Connectez-vous à {name}", { name: SITE_NAME })}</h1>
            <p className="mt-2 mb-6 text-sm leading-relaxed text-muted">{t("Saisissez votre e-mail : nous vous envoyons un code. Votre compte est créé automatiquement à la première connexion.")}</p>
            <LoginForm next={next} />
          </div>
          <ul className="mt-6 space-y-2.5">
            {points.map(({ Icon, text }) => (
              <li key={text} className="flex items-center gap-3 text-sm font-medium text-ink/75">
                <Icon className="size-4 text-brand" aria-hidden="true" />
                {text}
              </li>
            ))}
          </ul>
        </div>
      </div>
    </div>
  );
}
