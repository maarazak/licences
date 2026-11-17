import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { AdminLoginForm } from "@/components/admin-login-form";
import { Logo } from "@/components/site-chrome";
import { currentUser, isAdmin } from "@/server/auth/session";

// Page volontairement absente du site, du plan de site et des moteurs de recherche : les administrateurs saisissent l'adresse.
export const metadata: Metadata = { title: "Connexion", robots: { index: false, follow: false } };

export default async function AdminLoginPage() {
  if (isAdmin(await currentUser())) redirect("/admin");
  return (
    <main className="flex min-h-dvh items-center justify-center px-4 py-12">
      <div className="w-full max-w-sm">
        <div className="mb-6 flex justify-center">
          <Logo href="/admin/connexion" />
        </div>
        <div className="card p-6 shadow-pop sm:p-8">
          <h1 className="h2">Accès administrateur</h1>
          <p className="mt-2 mb-6 text-sm text-muted">Réservé à l&apos;équipe. Saisissez votre e-mail et votre mot de passe.</p>
          <AdminLoginForm />
        </div>
      </div>
    </main>
  );
}
