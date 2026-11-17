import type { Metadata, Viewport } from "next";
import { Sora, Inter } from "next/font/google";
import { I18nProvider } from "@/components/i18n";
import { EN, EN_PATTERNS } from "@/lib/i18n-en";
import { SITE_NAME } from "@/lib/site";
import { getI18n, getLang } from "@/server/i18n";
import { getMoney } from "@/server/prefs";
import "./globals.css";

const display = Sora({ subsets: ["latin"], weight: ["600", "700", "800"], variable: "--font-display-face", display: "swap" });
const body = Inter({ subsets: ["latin"], variable: "--font-body-face", display: "swap" });

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return {
  metadataBase: new URL(process.env.APP_URL ?? "http://localhost:3000"),
  title: { default: `${SITE_NAME} — ${t("Abonnements et licences numériques, payés en FCFA")}`, template: `%s | ${SITE_NAME}` },
  description: t("{name} : abonnements et licences numériques payables en FCFA par mobile money. Livraison rapide, accès conservés dans votre espace.", { name: SITE_NAME }),
  robots: { index: true, follow: true },
  // Le site gère lui-même le français et l'anglais : le navigateur ne doit pas retraduire les pages.
  other: { google: "notranslate" },
  };
}

export const viewport: Viewport = { themeColor: "#0d5c4a", width: "device-width", initialScale: 1 };

export default async function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  const [lang, money] = await Promise.all([getLang(), getMoney()]);
  return (
    <html lang={lang} translate="no" className={`${display.variable} ${body.variable}`} suppressHydrationWarning>
      <body className="min-h-dvh">
        <I18nProvider lang={lang} dict={lang === "en" ? EN : {}} patterns={lang === "en" ? EN_PATTERNS : []} currency={money.currency} rate={money.rate}>
          {children}
        </I18nProvider>
      </body>
    </html>
  );
}
