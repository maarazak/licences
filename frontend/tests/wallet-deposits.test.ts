import { afterAll, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/headers", () => ({
  headers: async () => new Headers(),
  cookies: async () => ({ get: () => undefined, set: () => {} }),
}));

import { POST as webhook } from "@/app/api/webhooks/pawapay/route";
import { createUser, db, eq, fake, resetDb, schema } from "./integration/harness";
import {
  getUserWalletDeposit,
  listWalletEntries,
  startWalletDeposit,
  syncWalletDeposit,
} from "@/server/services/wallet";

beforeEach(async () => {
  await resetDb();
});

afterAll(async () => {
  const g = globalThis as unknown as { __pool?: { end(): Promise<void> } };
  await g.__pool?.end();
});

describe("dépôt de solde par Mobile Money", () => {
  it("initialise un dépôt et renvoie l'URL de paiement hébergée", async () => {
    const user = await createUser({ balanceXof: 0 });
    const { depositId, reference, paymentUrl } = await startWalletDeposit(user, {
      amountXof: 5000,
      phone: "70001122",
      country: "BF",
      fullName: "Test User",
    });

    expect(depositId).toBeDefined();
    expect(reference).toMatch(/^DEP-/);
    expect(paymentUrl).toContain("paywith.sandbox.pawapay.io");

    const deposit = await getUserWalletDeposit(user.id, depositId);
    expect(deposit).toBeDefined();
    expect(deposit?.status).toBe("pending");
    expect(deposit?.amountXof).toBe(5000);
    expect(deposit?.customerPhone).toBe("70001122");
    expect(deposit?.customerCountry).toBe("BF");
  });

  it("crédite le solde du client une fois le paiement confirmé", async () => {
    const user = await createUser({ balanceXof: 1000 });
    const { depositId } = await startWalletDeposit(user, {
      amountXof: 10000,
      phone: "70001122",
      country: "BF",
    });

    fake.setPayment("completed");
    const updated = await syncWalletDeposit(depositId);

    expect(updated?.status).toBe("completed");
    expect(updated?.paidAt).toBeDefined();
    expect(updated?.creditedAt).toBeDefined();

    // Vérifie que le solde de l'utilisateur a augmenté de 10 000 FCFA
    const [freshUser] = await db().select().from(schema.users).where(eq(schema.users.id, user.id));
    expect(freshUser.balanceXof).toBe(11000);

    // Vérifie qu'une écriture a été enregistrée dans l'historique
    const entries = await listWalletEntries(user.id);
    expect(entries.length).toBe(1);
    expect(entries[0].amountXof).toBe(10000);
    expect(entries[0].balanceAfterXof).toBe(11000);
    expect(entries[0].kind).toBe("deposit");
  });

  it("est strictement idempotent et ne crédite pas deux fois lors d'appels répétés", async () => {
    const user = await createUser({ balanceXof: 500 });
    const { depositId } = await startWalletDeposit(user, {
      amountXof: 2500,
      phone: "70001122",
      country: "BF",
    });

    fake.setPayment("completed");

    // Plusieurs appels simultanés de synchronisation
    await Promise.all([
      syncWalletDeposit(depositId),
      syncWalletDeposit(depositId),
      syncWalletDeposit(depositId),
    ]);

    const [freshUser] = await db().select().from(schema.users).where(eq(schema.users.id, user.id));
    expect(freshUser.balanceXof).toBe(3000); // 500 + 2500 exactement une seule fois

    const entries = await listWalletEntries(user.id);
    expect(entries.length).toBe(1);
  });

  it("enregistre l'échec sans créditer si le paiement est rejeté", async () => {
    const user = await createUser({ balanceXof: 2000 });
    const { depositId } = await startWalletDeposit(user, {
      amountXof: 5000,
      phone: "70001122",
      country: "BF",
    });

    fake.setPayment("failed");
    const updated = await syncWalletDeposit(depositId);

    expect(updated?.status).toBe("failed");
    expect(updated?.creditedAt).toBeNull();

    const [freshUser] = await db().select().from(schema.users).where(eq(schema.users.id, user.id));
    expect(freshUser.balanceXof).toBe(2000);

    const entries = await listWalletEntries(user.id);
    expect(entries.length).toBe(0);
  });

  it("crédite le solde automatiquement via le webhook pawaPay", async () => {
    const user = await createUser({ balanceXof: 0 });
    const { depositId } = await startWalletDeposit(user, {
      amountXof: 15000,
      phone: "70001122",
      country: "BF",
    });

    fake.setPayment("completed");

    const req = new Request("https://boutique.test/api/webhooks/pawapay", {
      method: "POST",
      body: JSON.stringify({ event: "deposit.completed", depositId }),
    });

    const res = await webhook(req);
    expect(res.status).toBe(200);

    const [freshUser] = await db().select().from(schema.users).where(eq(schema.users.id, user.id));
    expect(freshUser.balanceXof).toBe(15000);
  });
});
