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
vi.mock("@/server/security/request", () => ({ clientIp: async () => "203.0.113.9", userAgent: async () => "test" }));
vi.mock("next/headers", () => ({ headers: async () => new Headers(), cookies: async () => ({ get: () => undefined, set: () => {} }) }));

import { balanceOf, checkoutInput, createProduct, createUser, db, eq, events, fake, getOrder, product, resetDb, schema, setOrder } from "./harness";
import * as orders from "@/server/services/orders";
import { processReminders } from "@/server/services/reminders";
import { syncCatalog } from "@/server/services/catalog";
import { POST as webhook } from "@/app/api/webhooks/pawapay/route";

const PRICE = 4250; // coût 3 $ × 669 = 2 007 ; max(×2 (marge 100 %) = 4 014 ; +1 000 = 3 007) → arrondi 250 → 4 250

beforeEach(async () => {
  await resetDb();
  mails.length = 0;
  mailFails.value = false;
});
afterAll(async () => {
  const g = globalThis as unknown as { __pool?: { end(): Promise<void> } };
  await g.__pool?.end();
});

async function pendingOrder(quantity = 1) {
  const user = await createUser();
  const prod = await createProduct();
  const { orderId, paymentUrl } = await orders.startCheckout(user, checkoutInput(prod.id, PRICE * quantity, { quantity }));
  return { user, prod, orderId, paymentUrl };
}

function webhookCall(paymentId: string) {
  return webhook(new Request("https://boutique.test/api/webhooks/pawapay", { method: "POST", body: JSON.stringify({ event: "deposit.completed", depositId: paymentId }) }));
}

describe("paiement pawaPay puis livraison", () => {
  it("paiement confirmé → livrée, un seul achat, accès chiffrés lisibles", async () => {
    const { orderId, paymentUrl } = await pendingOrder();
    expect(paymentUrl).toMatch(/^https:\/\/paywith\.sandbox\.pawapay\.io\//);
    expect((await getOrder(orderId)).status).toBe("pending_payment");
    fake.setPayment("completed");
    const o = await orders.syncPayment(orderId);
    expect(o?.status).toBe("delivered");
    expect(fake.purchases).toBe(1);
    expect(orders.readDelivery(o!)).toEqual(["compte1@exemple.test | MotDePasse-1"]);
    expect(o!.deliveryEncrypted).not.toContain("MotDePasse");
  });

  it("le retour client avant paiement ne change rien (preuve = relecture pawaPay)", async () => {
    const { orderId } = await pendingOrder();
    await orders.syncPayment(orderId);
    expect((await getOrder(orderId)).status).toBe("pending_payment");
    expect(fake.purchases).toBe(0);
  });

  it("webhook rejoué 3 fois → un seul achat fournisseur, un seul événement « paid »", async () => {
    const { orderId } = await pendingOrder();
    fake.setPayment("completed");
    const pi = (await getOrder(orderId)).paymentIntentId!;
    for (let i = 0; i < 3; i++) expect((await webhookCall(pi)).status).toBe(200);
    expect((await getOrder(orderId)).status).toBe("delivered");
    expect(fake.purchases).toBe(1);
    expect(await events(orderId, "paid")).toHaveLength(1);
  });

  it("webhook + retour client + tâche planifiée simultanés → un seul achat, un seul e-mail « payée »", async () => {
    const { orderId } = await pendingOrder();
    await setOrder(orderId, { createdAt: new Date(Date.now() - 5 * 60_000) });
    fake.setPayment("completed");
    fake.supplierDelayMs = 150;
    const pi = (await getOrder(orderId)).paymentIntentId!;
    await Promise.all([webhookCall(pi), webhookCall(pi), orders.syncPaymentThrottled(orderId), orders.syncPayment(orderId), orders.runOrderMaintenance(), orders.fulfil(orderId)]);
    expect((await getOrder(orderId)).status).toBe("delivered");
    expect(fake.createOrderCalls).toBe(1);
    expect(fake.purchases).toBe(1);
    expect(await events(orderId, "paid")).toHaveLength(1);
    expect(mails.filter((m) => m.text.includes("reçu") || /pay/i.test(m.subject)).length).toBeGreaterThanOrEqual(1);
  });

  it("webhook avec identifiant inconnu ou JSON invalide : aucun effet", async () => {
    const { orderId } = await pendingOrder();
    fake.setPayment("completed");
    expect((await webhookCall("pi_inconnu")).status).toBe(200);
    expect((await webhook(new Request("https://b.test/x", { method: "POST", body: "{" }))).status).toBe(400);
    expect((await getOrder(orderId)).status).toBe("pending_payment");
  });
});

describe("paiement tardif", () => {
  it("commande expirée chez pawaPay puis paiement abouti : le webhook la reprend et la livre", async () => {
    const { orderId } = await pendingOrder();
    fake.setPayment("expired");
    expect((await orders.syncPayment(orderId))?.status).toBe("expired");
    fake.setPayment("completed");
    await webhookCall((await getOrder(orderId)).paymentIntentId!);
    expect((await getOrder(orderId)).status).toBe("delivered");
    expect(fake.purchases).toBe(1);
  });

  it("[BUG] sans webhook, la tâche planifiée ne relit jamais une commande expirée : paiement tardif jamais détecté", async () => {
    const { orderId } = await pendingOrder();
    fake.setPayment("expired");
    await orders.syncPayment(orderId);
    fake.setPayment("completed");
    await orders.runOrderMaintenance();
    await processReminders();
    // Comportement attendu : « delivered ». Observé : la commande reste « expired » (argent encaissé, rien livré).
    expect((await getOrder(orderId)).status).toBe("delivered");
  });

  it("[BUG] après 48 h, pawaPay injoignable : la commande est close « expirée » sans preuve de non-paiement", async () => {
    const { orderId } = await pendingOrder();
    await setOrder(orderId, { createdAt: new Date(Date.now() - 49 * 3600_000) });
    fake.setPayment("completed"); // le client a payé
    fake.orqexDown = true; // mais Orqex ne répond pas au moment de la tâche
    await orders.runOrderMaintenance();
    expect((await getOrder(orderId)).status).toBe("pending_payment");
  });
});

describe("montant payé différent", () => {
  it("montant inférieur → « failed / amount_mismatch », pas d'achat, aucun dédommagement automatique", async () => {
    const { user, orderId } = await pendingOrder();
    const c = [...fake.checkouts.values()][0];
    c.amountOverride = PRICE - 100;
    fake.setPayment("completed");
    const o = await orders.syncPayment(orderId);
    expect(o?.status).toBe("failed");
    expect(o?.failureCode).toBe("amount_mismatch");
    expect(fake.createOrderCalls).toBe(0);
    expect(await orders.customerChooseCredit(user.id, orderId)).toBe(false);
    expect(await orders.customerRequestRefund(user.id, orderId)).toBe(false);
    expect(await orders.adminCreditOrder(orderId, "admin")).toBe(false);
    expect(await orders.adminRetryFulfilment(orderId, "admin")).toBe(false);
    expect(await balanceOf(user.id)).toBe(0);
  });

  it("devise différente (même valeur) → refusée", async () => {
    const { orderId } = await pendingOrder();
    [...fake.checkouts.values()][0].currency = "EUR";
    fake.setPayment("completed");
    expect((await orders.syncPayment(orderId))?.failureCode).toBe("amount_mismatch");
  });

  it("identifiant de paiement relu différent de celui enregistré → la commande n'avance pas", async () => {
    const { orderId } = await pendingOrder();
    [...fake.checkouts.values()][0].paymentIdOverride = "pi_autre_tentative";
    fake.setPayment("completed");
    expect((await orders.syncPayment(orderId))?.status).toBe("pending_payment");
  });
});

describe("échec du fournisseur, avoir et remboursement", () => {
  async function failedOrder(mode = "delivery_failed") {
    const r = await pendingOrder();
    fake.nextOrder = mode;
    fake.setPayment("completed");
    await orders.syncPayment(r.orderId);
    return r;
  }

  it("échec définitif → failed ; avoir demandé 5 fois en parallèle → crédité une seule fois", async () => {
    const { user, orderId } = await failedOrder();
    expect((await getOrder(orderId)).status).toBe("failed");
    expect(orders.canCustomerResolve(await getOrder(orderId))).toBe(true);
    const results = await Promise.all(Array.from({ length: 5 }, () => orders.customerChooseCredit(user.id, orderId)));
    expect(results.filter(Boolean)).toHaveLength(1);
    expect(await balanceOf(user.id)).toBe(PRICE);
    const entries = await db().select().from(schema.walletEntries).where(eq(schema.walletEntries.userId, user.id));
    expect(entries).toHaveLength(1);
    expect(entries[0].balanceAfterXof).toBe(PRICE);
    expect((await getOrder(orderId)).status).toBe("refunded");
    // Plus de relance possible après l'avoir.
    expect(await orders.adminRetryFulfilment(orderId, "admin")).toBe(false);
  });

  it("un autre client ne peut pas prendre l'avoir", async () => {
    const { orderId } = await failedOrder();
    const intrus = await createUser();
    expect(await orders.customerChooseCredit(intrus.id, orderId)).toBe(false);
    expect(await balanceOf(intrus.id)).toBe(0);
  });

  it("avoir et remboursement demandés en même temps → un seul des deux", async () => {
    const { user, orderId } = await failedOrder();
    const [credit, refund] = await Promise.all([orders.customerChooseCredit(user.id, orderId), orders.customerRequestRefund(user.id, orderId)]);
    expect(Number(credit) + Number(refund)).toBe(1);
    const o = await getOrder(orderId);
    if (credit) expect(o.refundRequestedAt).toBeNull();
    else expect(await balanceOf(user.id)).toBe(0);
  });

  it("remboursement demandé puis avoir client → refusé", async () => {
    const { user, orderId } = await failedOrder();
    expect(await orders.customerRequestRefund(user.id, orderId)).toBe(true);
    expect(await orders.customerRequestRefund(user.id, orderId)).toBe(false);
    expect(await orders.customerChooseCredit(user.id, orderId)).toBe(false);
    expect(await balanceOf(user.id)).toBe(0);
  });

  it("[BUG] remboursement demandé, puis avoir accordé par un admin : la commande est « remboursée par avoir » alors que le remboursement chez le prestataire est toujours demandé", async () => {
    const { user, orderId } = await failedOrder();
    await orders.customerRequestRefund(user.id, orderId);
    const credited = await orders.adminCreditOrder(orderId, "admin@boutique.test");
    // Attendu : refus (double dédommagement possible : avoir + remboursement Orqex émis sur l'alerte « Remboursement demandé »).
    expect(credited).toBe(false);
  });

  it("[BUG] remboursement demandé, puis relance admin → livrée alors que le remboursement reste demandé (livré ET remboursé)", async () => {
    const { user, orderId } = await failedOrder();
    await orders.customerRequestRefund(user.id, orderId);
    fake.nextOrder = "completed";
    const retried = await orders.adminRetryFulfilment(orderId, "admin@boutique.test");
    const o = await getOrder(orderId);
    // Attendu : refus tant que la demande de remboursement n'est pas levée.
    expect(retried && o.refundRequestedAt !== null).toBe(false);
  });

  it("relance admin après échec définitif → nouvelle clé d'idempotence, livrée", async () => {
    const { orderId } = await failedOrder();
    const before = (await getOrder(orderId)).supplierIdemKey;
    fake.nextOrder = "completed";
    expect(await orders.adminRetryFulfilment(orderId, "admin")).toBe(true);
    const o = await getOrder(orderId);
    expect(o.status).toBe("delivered");
    expect(o.supplierIdemKey).not.toBe(before);
    expect(fake.purchases).toBe(1);
  });

  it("solde fournisseur vidé entre commande et paiement → failed, le client peut choisir l'avoir", async () => {
    const { user, orderId } = await pendingOrder();
    fake.balance = 0;
    fake.setPayment("completed");
    await orders.syncPayment(orderId);
    expect((await getOrder(orderId)).failureCode).toBe("insufficient_balance");
    expect(await orders.customerChooseCredit(user.id, orderId)).toBe(true);
  });
});

describe("issue incertaine chez le fournisseur", () => {
  it("achat débité mais réponse perdue → reprise avec la même clé → un seul achat, livrée", async () => {
    const { orderId } = await pendingOrder();
    fake.nextOrder = "timeout_after_purchase";
    fake.setPayment("completed");
    await orders.syncPayment(orderId);
    let o = await getOrder(orderId);
    expect(o.status).toBe("processing");
    expect(fake.purchases).toBe(1);
    // Reprise avant expiration du verrou : rien.
    await orders.fulfil(orderId);
    expect(fake.createOrderCalls).toBe(1);
    // Verrou expiré : la tâche planifiée reprend avec la même clé.
    fake.nextOrder = "completed";
    await setOrder(orderId, { fulfilLockedAt: new Date(Date.now() - 4 * 60_000) });
    await orders.runOrderMaintenance();
    o = await getOrder(orderId);
    expect(o.status).toBe("delivered");
    expect(fake.purchases).toBe(1);
    expect(fake.createOrderCalls).toBe(2);
  });

  it("trop d'essais → failed bloqué (le client ne choisit pas seul), relance admin avec la même clé", async () => {
    const { user, orderId } = await pendingOrder();
    fake.nextOrder = "network_error";
    fake.setPayment("completed");
    await orders.syncPayment(orderId);
    const key = (await getOrder(orderId)).supplierIdemKey;
    for (let i = 0; i < 12; i++) {
      await setOrder(orderId, { fulfilLockedAt: new Date(Date.now() - 4 * 60_000) });
      await orders.fulfil(orderId);
    }
    const o = await getOrder(orderId);
    expect(o.status).toBe("failed");
    expect(o.failureCode).toBe("too_many_attempts");
    expect(orders.canCustomerResolve(o)).toBe(false);
    expect(await orders.customerChooseCredit(user.id, orderId)).toBe(false);
    fake.nextOrder = "completed";
    expect(await orders.adminRetryFulfilment(orderId, "admin")).toBe(true);
    expect((await getOrder(orderId)).supplierIdemKey).toBe(key);
    expect((await getOrder(orderId)).status).toBe("delivered");
  });

  it("activation manuelle puis terminée → livrée par interrogation", async () => {
    const { orderId } = await pendingOrder();
    fake.nextOrder = "activation_awaiting";
    fake.setPayment("completed");
    await orders.syncPayment(orderId);
    const o = await getOrder(orderId);
    expect(o.status).toBe("activation_awaiting");
    const so = fake.supplierOrders.get(o.supplierOrderId!)!;
    so.status = "completed";
    so.items = ["code-ABC"];
    await orders.runOrderMaintenance();
    expect((await getOrder(orderId)).status).toBe("delivered");
    expect(fake.purchases).toBe(1);
  });
});

describe("paiement par solde", () => {
  async function fundedUser(amount: number) {
    const user = await createUser();
    await db().transaction(async (tx) => {
      const { creditWallet } = await import("@/server/services/wallet");
      await creditWallet(tx, { userId: user.id, amountXof: amount, kind: "admin_adjustment", label: "test" });
    });
    return user;
  }

  it("solde exactement suffisant, double clic → une seule commande, solde jamais négatif", async () => {
    const user = await fundedUser(PRICE);
    const prod = await createProduct();
    const res = await Promise.allSettled([1, 2, 3].map(() => orders.startCheckout(user, checkoutInput(prod.id, PRICE, { payWithBalance: true }))));
    expect(res.filter((r) => r.status === "fulfilled")).toHaveLength(1);
    expect(await balanceOf(user.id)).toBe(0);
    const list = await db().select().from(schema.orders).where(eq(schema.orders.userId, user.id));
    expect(list).toHaveLength(1);
    expect(list[0].status).toBe("delivered");
    expect(list[0].paidWith).toBe("balance");
    expect(fake.purchases).toBe(1);
  });

  it("solde insuffisant → refus, aucune commande, aucun débit", async () => {
    const user = await fundedUser(PRICE - 1);
    const prod = await createProduct();
    await expect(orders.startCheckout(user, checkoutInput(prod.id, PRICE, { payWithBalance: true }))).rejects.toThrow(/solde/);
    expect(await balanceOf(user.id)).toBe(PRICE - 1);
    expect(await db().select().from(schema.orders)).toHaveLength(0);
  });

  it("[BUG] double clic avec un solde couvrant deux achats → deux commandes payées et deux achats fournisseur", async () => {
    const user = await fundedUser(PRICE * 2);
    const prod = await createProduct();
    const input = checkoutInput(prod.id, PRICE, { payWithBalance: true, clientToken: "11111111-1111-4111-8111-111111111111" });
    await Promise.allSettled([1, 2].map(() => orders.startCheckout(user, input)));
    const list = await db().select().from(schema.orders).where(eq(schema.orders.userId, user.id));
    // Attendu : une seule commande pour une seule validation (aucune clé d'idempotence côté formulaire).
    expect(list).toHaveLength(1);
  });

  it("commande payée par solde en échec → avoir oui, remboursement prestataire non", async () => {
    const user = await fundedUser(PRICE);
    const prod = await createProduct();
    fake.nextOrder = "delivery_failed";
    const { orderId } = await orders.startCheckout(user, checkoutInput(prod.id, PRICE, { payWithBalance: true }));
    expect((await getOrder(orderId)).status).toBe("failed");
    expect(await orders.customerRequestRefund(user.id, orderId)).toBe(false);
    expect(await orders.adminMarkRefunded(orderId, "admin")).toBe(false);
    expect(await orders.customerChooseCredit(user.id, orderId)).toBe(true);
    expect(await balanceOf(user.id)).toBe(PRICE);
  });
});

describe("prix", () => {
  it("total attendu falsifié → refus", async () => {
    const user = await createUser();
    const prod = await createProduct();
    await expect(orders.startCheckout(user, checkoutInput(prod.id, 100))).rejects.toThrow(/prix/);
    expect(await db().select().from(schema.orders)).toHaveLength(0);
  });

  it("coût fournisseur en hausse entre commande et livraison, vente à perte → failed price_changed, aucun achat", async () => {
    const { user, orderId } = await pendingOrder();
    fake.products[0].price = 7; // 7 $ × 669 = 4 683 > 4 250
    fake.setPayment("completed");
    await orders.syncPayment(orderId);
    const o = await getOrder(orderId);
    expect(o.failureCode).toBe("price_changed");
    expect(fake.createOrderCalls).toBe(0);
    expect(await orders.customerChooseCredit(user.id, orderId)).toBe(true);
  });

  it("hausse absorbée par la marge → livrée", async () => {
    const { orderId } = await pendingOrder();
    fake.products[0].price = 6; // 6 $ × 669 = 4 014 < 4 250
    fake.setPayment("completed");
    await orders.syncPayment(orderId);
    expect((await getOrder(orderId)).status).toBe("delivered");
  });

  it("le prix affiché change pendant la saisie → refus « le prix a changé »", async () => {
    const user = await createUser();
    const prod = await createProduct();
    fake.products[0].price = 5;
    await expect(orders.startCheckout(user, checkoutInput(prod.id, PRICE))).rejects.toThrow(/changé/);
  });

  it("quantité au-delà du stock fournisseur → refus", async () => {
    const user = await createUser();
    const prod = await createProduct();
    fake.products[0].stock = 1;
    await expect(orders.startCheckout(user, checkoutInput(prod.id, PRICE * 2, { quantity: 2 }))).rejects.toThrow(/reste/);
  });
});

describe("relances de paiement", () => {
  it("étape 1 : une seule relance même si la tâche passe deux fois (ou en parallèle)", async () => {
    const { orderId } = await pendingOrder();
    await setOrder(orderId, { createdAt: new Date(Date.now() - 30 * 60_000) });
    await Promise.all([processReminders(), processReminders()]);
    await processReminders();
    expect((await getOrder(orderId)).reminderCount).toBe(1);
    expect(await events(orderId, "reminder")).toHaveLength(1);
  });

  it("étape 1 : pas de relance si le paiement vient d'aboutir (et la commande est livrée)", async () => {
    const { orderId } = await pendingOrder();
    await setOrder(orderId, { createdAt: new Date(Date.now() - 30 * 60_000) });
    fake.setPayment("completed");
    await processReminders();
    const o = await getOrder(orderId);
    expect(o.reminderCount).toBe(0);
    expect(o.status).toBe("delivered");
  });

  it("étape 2 : une seule relance après expiration ; aucune ensuite", async () => {
    const { orderId } = await pendingOrder();
    fake.setPayment("expired");
    await orders.syncPayment(orderId);
    await setOrder(orderId, { createdAt: new Date(Date.now() - 2 * 3600_000) });
    await processReminders();
    await processReminders();
    expect((await getOrder(orderId)).reminderCount).toBe(2);
    expect(await events(orderId, "reminder")).toHaveLength(1);
  });

  it("étape 1 puis étape 2 : deux relances au total, jamais plus", async () => {
    const { orderId } = await pendingOrder();
    await setOrder(orderId, { createdAt: new Date(Date.now() - 30 * 60_000) });
    await processReminders();
    fake.setPayment("expired");
    await orders.syncPayment(orderId);
    await setOrder(orderId, { createdAt: new Date(Date.now() - 2 * 3600_000) });
    await processReminders();
    await processReminders();
    expect(await events(orderId, "reminder")).toHaveLength(2);
  });

  it("échec d'envoi → réservation libérée, renvoyée au passage suivant (une seule fois)", async () => {
    const { orderId } = await pendingOrder();
    await setOrder(orderId, { createdAt: new Date(Date.now() - 30 * 60_000) });
    mailFails.value = true;
    await processReminders();
    expect((await getOrder(orderId)).reminderCount).toBe(0);
    mailFails.value = false;
    await processReminders();
    await processReminders();
    expect(await events(orderId, "reminder")).toHaveLength(1);
  });

  it("[BUG] étape 2 envoyée à un client dont le paiement a abouti tardivement (pas de relecture pawaPay avant l'étape 2)", async () => {
    const { orderId } = await pendingOrder();
    fake.setPayment("expired");
    await orders.syncPayment(orderId);
    await setOrder(orderId, { createdAt: new Date(Date.now() - 2 * 3600_000) });
    fake.setPayment("completed"); // payé tardivement, webhook perdu
    await processReminders();
    // Attendu : pas d'e-mail « recommencez votre commande » à un client qui a payé.
    expect(await events(orderId, "reminder")).toHaveLength(0);
  });

  it("[BUG] étape 1 envoyée pour une commande en double alors que le client a déjà payé la même offre (pas de contrôle « a donné suite »)", async () => {
    const user = await createUser();
    const prod = await createProduct();
    const a = await orders.startCheckout(user, checkoutInput(prod.id, PRICE));
    const b = await orders.startCheckout(user, checkoutInput(prod.id, PRICE)); // double clic
    fake.setPayment("completed", undefined, (await getOrder(a.orderId)).paymentCheckoutId!);
    await orders.syncPayment(a.orderId);
    expect((await getOrder(a.orderId)).status).toBe("delivered");
    await setOrder(b.orderId, { createdAt: new Date(Date.now() - 30 * 60_000) });
    await processReminders();
    // Attendu : aucune relance « terminez votre paiement » (le client risque de payer deux fois).
    expect(await events(b.orderId, "reminder")).toHaveLength(0);
  });

  it("pas de relance si le client a désactivé les rappels", async () => {
    const user = await createUser({ mailReminders: false });
    const prod = await createProduct();
    const { orderId } = await orders.startCheckout(user, checkoutInput(prod.id, PRICE));
    await setOrder(orderId, { createdAt: new Date(Date.now() - 30 * 60_000) });
    await processReminders();
    expect((await getOrder(orderId)).reminderCount).toBe(0);
  });

  it("reprise de paiement : régénère automatiquement une URL active si l'ancienne a expiré", async () => {
    const user = await createUser();
    const prod = await createProduct();
    const { orderId } = await orders.startCheckout(user, checkoutInput(prod.id, PRICE));

    // Simule une URL dont la signature CloudFront a expiré dans le passé
    const pastUnix = Math.floor(Date.now() / 1000) - 300;
    await setOrder(orderId, { paymentUrl: `https://paywith.sandbox.pawapay.io/checkout/expired?Expires=${pastUnix}` });

    const freshUrl = await orders.getFreshOrderPaymentUrl(orderId, user);
    expect(freshUrl).toBeDefined();
    expect(freshUrl).not.toContain("expired");

    const currentOrder = await getOrder(orderId);
    expect(currentOrder.paymentUrl).toBe(freshUrl);
  });
});

describe("synchronisation du catalogue", () => {
  it("[BUG] produit passé à un coût nul chez le fournisseur : jamais mis à jour ni désactivé, reste affiché disponible à l'ancien prix", async () => {
    const prod = await createProduct();
    fake.products[0].price = 0;
    fake.products[0].in_stock = false;
    await syncCatalog();
    const [row] = await db().select().from(schema.products).where(eq(schema.products.id, prod.id));
    // Attendu : in_stock = false (au minimum) ; observé : l'ancien état « en stock » est conservé.
    expect(row.inStock).toBe(false);
  });

  it("produit disparu → désactivé ; liste vide → rien désactivé ; slug en double → suffixé", async () => {
    const prod = await createProduct();
    fake.products = [product("sup-2", "netflix-1-mois", "Netflix 1 mois bis", 2)];
    const r = await syncCatalog();
    expect(r.created).toBe(1);
    expect(r.deactivated).toBe(1);
    const rows = await db().select().from(schema.products);
    expect(new Set(rows.map((p) => p.slug)).size).toBe(2);
    expect(rows.find((p) => p.id === prod.id)?.supplierActive).toBe(false);
    fake.products = [];
    expect((await syncCatalog()).deactivated).toBe(0);
  });

  it("synchronisation incrémentale : rien n'est réécrit si rien n'a changé ; prix, stock et état changés → mis à jour", async () => {
    const prod = await createProduct();
    await syncCatalog();
    const again = await syncCatalog();
    expect(again).toMatchObject({ created: 0, updated: 0, unchanged: 1 });

    fake.products[0].price = fake.products[0].price + 1;
    fake.products[0].stock = 7;
    expect(await syncCatalog()).toMatchObject({ updated: 1, unchanged: 0 });
    const [row] = await db().select().from(schema.products).where(eq(schema.products.id, prod.id));
    expect(row.stock).toBe(7);
    expect(row.costUsdCents).toBe(Math.round(fake.products[0].price * 100));
  });

  it("les réglages boutique survivent à la synchronisation (publication, nom affiché, prix fixe)", async () => {
    const prod = await createProduct();
    await db().update(schema.products).set({ isPublished: true, displayName: "Mon nom", priceXofOverride: 5000 }).where(eq(schema.products.id, prod.id));
    fake.products[0].price = fake.products[0].price + 1;
    await syncCatalog();
    const [row] = await db().select().from(schema.products).where(eq(schema.products.id, prod.id));
    expect(row).toMatchObject({ isPublished: true, displayName: "Mon nom", priceXofOverride: 5000 });
  });

  it("fournisseur qui annonce à tort « stock 0 / non vendable » : la dégradation n'est enregistrée qu'après 5 lectures consécutives", async () => {
    const prod = await createProduct();
    await syncCatalog();
    const flap = () => {
      fake.products[0].stock = 0;
      fake.products[0].in_stock = false;
      fake.products[0].purchasable = false;
    };
    flap();
    for (let i = 1; i <= 4; i++) {
      const r = await syncCatalog();
      expect(r.held).toBe(1);
      const [row] = await db().select().from(schema.products).where(eq(schema.products.id, prod.id));
      expect(row.inStock).toBe(true);
      expect(row.supplierPurchasable).toBe(true);
      expect(row.unavailableReadings).toBe(i);
    }
    await syncCatalog(); // 5e lecture concordante : confirmé
    const [confirmed] = await db().select().from(schema.products).where(eq(schema.products.id, prod.id));
    expect(confirmed).toMatchObject({ inStock: false, supplierPurchasable: false, stock: 0, unavailableReadings: 0 });
  });

  it("le fournisseur se corrige avant confirmation : le compteur repart de zéro, aucune indisponibilité enregistrée", async () => {
    const prod = await createProduct();
    await syncCatalog();
    fake.products[0].stock = 0;
    fake.products[0].in_stock = false;
    fake.products[0].purchasable = false;
    await syncCatalog();
    await syncCatalog();
    fake.products[0].stock = 3;
    fake.products[0].in_stock = true;
    fake.products[0].purchasable = true;
    await syncCatalog();
    const [row] = await db().select().from(schema.products).where(eq(schema.products.id, prod.id));
    expect(row).toMatchObject({ inStock: true, supplierPurchasable: true, unavailableReadings: 0 });
  });

  it("produit enregistré indisponible après confirmation : la première lecture « disponible » le remet en vente tout de suite", async () => {
    const prod = await createProduct();
    await syncCatalog();
    fake.products[0].stock = 0;
    fake.products[0].in_stock = false;
    fake.products[0].purchasable = false;
    for (let i = 0; i < 5; i++) await syncCatalog();
    const [down] = await db().select().from(schema.products).where(eq(schema.products.id, prod.id));
    expect(down.inStock).toBe(false);
    fake.products[0].stock = 4;
    fake.products[0].in_stock = true;
    fake.products[0].purchasable = true;
    await syncCatalog(); // une seule lecture suffit
    const [up] = await db().select().from(schema.products).where(eq(schema.products.id, prod.id));
    expect(up).toMatchObject({ inStock: true, supplierPurchasable: true, stock: 4, unavailableReadings: 0 });
  });

  it("pendant l'attente de confirmation, le prix du fournisseur est quand même mis à jour", async () => {
    const prod = await createProduct();
    await syncCatalog();
    fake.products[0].in_stock = false;
    fake.products[0].price = fake.products[0].price + 1;
    await syncCatalog();
    const [held] = await db().select().from(schema.products).where(eq(schema.products.id, prod.id));
    expect(held.inStock).toBe(true);
    expect(held.costUsdCents).toBe(Math.round(fake.products[0].price * 100));
  });

  it("[BUG] deux synchronisations simultanées (tâche planifiée + consultation) → doublon sur products_supplier_uq, synchro interrompue", async () => {
    fake.products = [product("sup-9", "nouveau", "Nouveau", 2)];
    const res = await Promise.allSettled([syncCatalog(), syncCatalog()]);
    expect(res.filter((r) => r.status === "rejected")).toHaveLength(0);
  });
});
