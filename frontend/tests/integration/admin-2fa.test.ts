import { afterAll, beforeEach, describe, expect, it, vi } from "vitest";

const mails = vi.hoisted(() => [] as Array<{ to: string; subject: string; text: string }>);
const mailFails = vi.hoisted(() => ({ value: false }));
vi.mock("@/server/mail/mailer", () => ({
  sendMail: async (m: { to: string; subject: string; text: string }) => {
    if (mailFails.value) return false;
    mails.push(m);
    return true;
  },
}));
vi.mock("next/headers", () => ({ headers: async () => new Headers(), cookies: async () => ({ get: () => undefined, set: () => {} }) }));

import { createUser, db, resetDb, sql } from "./harness";
import { issueAdminChallenge, verifyAdminChallenge } from "@/server/auth/otp";

const codeFrom = () => /Code : (\d{6})/.exec(mails.at(-1)?.text ?? "")?.[1] ?? "";

beforeEach(async () => {
  await resetDb();
  await db().execute(sql.raw("DELETE FROM `login_codes`"));
  mails.length = 0;
  mailFails.value = false;
});
afterAll(async () => {
  const g = globalThis as unknown as { __pool?: { end(): Promise<void> } };
  await g.__pool?.end();
});

describe("double authentification administrateur", () => {
  it("le code est envoyé à l'adresse de l'administrateur et valide une seule fois", async () => {
    const admin = await createUser({ role: "admin" });
    const { token, sent } = await issueAdminChallenge(admin, "203.0.113.9");
    expect(sent).toBe(true);
    expect(mails.at(-1)?.to).toBe(admin.email);
    const code = codeFrom();
    expect(code).toMatch(/^\d{6}$/);
    expect(await verifyAdminChallenge(admin.id, token, code)).toBe("ok");
    expect(await verifyAdminChallenge(admin.id, token, code)).toBe("expired"); // usage unique
  });

  it("un mauvais code est refusé ; après 5 essais le défi est verrouillé même avec le bon code", async () => {
    const admin = await createUser({ role: "admin" });
    const { token } = await issueAdminChallenge(admin, "203.0.113.9");
    const good = codeFrom();
    const bad = good === "000000" ? "111111" : "000000";
    for (let i = 0; i < 5; i++) expect(await verifyAdminChallenge(admin.id, token, bad)).toBe("invalid");
    expect(await verifyAdminChallenge(admin.id, token, good)).toBe("locked");
  });

  it("sans le jeton du cookie (donc sans mot de passe), le code reçu ne sert à rien", async () => {
    const admin = await createUser({ role: "admin" });
    await issueAdminChallenge(admin, "203.0.113.9");
    const code = codeFrom();
    expect(await verifyAdminChallenge(admin.id, "jeton-devine-qui-ne-correspond-pas-123456", code)).toBe("expired");
  });

  it("le code d'un administrateur ne vaut pas pour un autre", async () => {
    const a = await createUser({ role: "admin" });
    const b = await createUser({ role: "admin" });
    const { token } = await issueAdminChallenge(a, "203.0.113.9");
    expect(await verifyAdminChallenge(b.id, token, codeFrom())).toBe("expired");
  });

  it("un nouveau défi invalide le précédent", async () => {
    const admin = await createUser({ role: "admin" });
    const first = await issueAdminChallenge(admin, "203.0.113.9");
    const firstCode = codeFrom();
    await issueAdminChallenge(admin, "203.0.113.9");
    expect(await verifyAdminChallenge(admin.id, first.token, firstCode)).toBe("expired");
  });

  it("e-mail non parti : l'échec est signalé (aucune connexion possible sans code)", async () => {
    const admin = await createUser({ role: "admin" });
    mailFails.value = true;
    expect((await issueAdminChallenge(admin, "203.0.113.9")).sent).toBe(false);
  });
});
