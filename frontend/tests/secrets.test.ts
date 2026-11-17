import { describe, expect, it } from "vitest";
import { isBlockedFile, scanLine } from "../scripts/check-secrets.mjs";

// Les valeurs de test sont assemblées à l'exécution : ce fichier ne contient lui-même aucun secret qui ressemble à un vrai.
const rand = (n: number) => "aB3dE5gH7jK9mN1pQ2sT4vW6yZ8".repeat(2).slice(0, n);

describe("vérification des secrets avant commit", () => {
  it("détecte les formats de clés connus", () => {
    expect(scanLine("const k = '" + "rk_live_" + rand(24) + "'")).toContain("clé API revendeur (rk_live_)");
    expect(scanLine("SMTP_PASSWORD=" + "xsmtpsib-" + rand(40))).toContain("clé SMTP/API Brevo");
    expect(scanLine("-----BEGIN " + "RSA PRIVATE KEY-----")).toContain("clé privée");
    expect(scanLine("token: " + "ghp_" + rand(36))).toContain("jeton GitHub");
  });

  it("détecte un secret affecté en clair et un mot de passe dans une adresse de base de données", () => {
    expect(scanLine('AUTH_SECRET="' + rand(40) + '"')).toContain("secret affecté en clair");
    expect(scanLine('const apiKey = "' + rand(30) + '";')).toContain("secret affecté en clair");
    expect(scanLine("DATABASE_URL=mysql://app:" + rand(14) + "@db.example.com/licences")).toContain("mot de passe de base de données dans une adresse");
  });

  it("détecte une valeur du .env local sans jamais la répéter", () => {
    const value = rand(30);
    const found = scanLine("// copie : " + value, [{ name: "CRON_SECRET", value }]);
    expect(found).toEqual(["valeur de CRON_SECRET (fichier .env local)"]);
    expect(JSON.stringify(found)).not.toContain(value);
  });

  it("laisse passer le code normal, les gabarits, les UUID et le développement local", () => {
    expect(scanLine('AUTH_SECRET=""')).toEqual([]);
    expect(scanLine("DATABASE_URL=mysql://root:@127.0.0.1:3306/licences")).toEqual([]);
    expect(scanLine('PAWAPAY_API_TOKEN="sandbox_<votre token>"')).toEqual([]);
    expect(scanLine('clientToken: "11111111-1111-4111-8111-111111111111",')).toEqual([]);
    expect(scanLine("const password = formData.get('password');")).toEqual([]);
    expect(scanLine("export const SESSION_SECRET_NAME = 'session';")).toEqual([]);
    expect(scanLine('AUTH_SECRET="' + rand(40) + '" // secret-scan: ignore')).toEqual([]);
  });

  it("bloque les fichiers secrets, mais autorise .env.example", () => {
    for (const f of [".env", ".env.local", ".env.production", "config/cert.pem", "a/b/server.key", "id_rsa"]) expect(isBlockedFile(f)).toBe(true);
    for (const f of [".env.example", "src/server/env.ts", "README.md", "id_rsa.pub"]) expect(isBlockedFile(f)).toBe(false);
  });
});
