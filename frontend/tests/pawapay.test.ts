import { describe, expect, it, vi } from "vitest";
import {
  PAWAPAY_XOF_COUNTRIES,
  createPaymentPage,
  formatPhoneForPawaPay,
  isPaymentUrlValid,
  isSafeCheckoutUrl,
  readDepositState,
  sanitizeCustomerMessage,
  sanitizeReason,
  sanitizeReturnUrl,
  toIso3Country,
} from "@/server/services/pawapay";

describe("service pawaPay", () => {
  describe("conversion code pays en ISO 3166-1 alpha-3", () => {
    it("convertit les pays de la zone UEMOA / CEMAC", () => {
      expect(toIso3Country("CI")).toBe("CIV");
      expect(toIso3Country("ci")).toBe("CIV");
      expect(toIso3Country("SN")).toBe("SEN");
      expect(toIso3Country("BF")).toBe("BFA");
      expect(toIso3Country("BJ")).toBe("BEN");
      expect(toIso3Country("ML")).toBe("MLI");
      expect(toIso3Country("TG")).toBe("TGO");
      expect(toIso3Country("CM")).toBe("CMR");
      expect(toIso3Country("CD")).toBe("COD");
    });

    it("préserve un code déjà sur 3 lettres", () => {
      expect(toIso3Country("CIV")).toBe("CIV");
      expect(toIso3Country("sen")).toBe("SEN");
    });

    it("gère les valeurs manquantes ou inconnues", () => {
      expect(toIso3Country(undefined)).toBeUndefined();
      expect(toIso3Country(null)).toBeUndefined();
      expect(toIso3Country("XX")).toBeUndefined();
    });
  });

  describe("formatage du numéro de téléphone mobile money", () => {
    it("retire le préfixe '+' et les espaces ou tirets", () => {
      expect(formatPhoneForPawaPay("+225 07 00 11 22")).toBe("22507001122");
      expect(formatPhoneForPawaPay("+221-77-123-4567")).toBe("221771234567");
      expect(formatPhoneForPawaPay("22507001122")).toBe("22507001122");
    });

    it("ajoute l'indicatif international si le numéro est saisi au format local", () => {
      expect(formatPhoneForPawaPay("70001122", "BF")).toBe("22670001122");
      expect(formatPhoneForPawaPay("70001122", "BFA")).toBe("22670001122");
      expect(formatPhoneForPawaPay("07001122", "CI")).toBe("22507001122");
    });

    it("rejette les numéros trop courts ou vides", () => {
      expect(formatPhoneForPawaPay("123")).toBeUndefined();
      expect(formatPhoneForPawaPay("")).toBeUndefined();
      expect(formatPhoneForPawaPay(undefined)).toBeUndefined();
    });
  });

  describe("sécurité des redirections de paiement (isSafeCheckoutUrl)", () => {
    it("autorise les sous-domaines officiels HTTPS de pawapay.io", () => {
      expect(isSafeCheckoutUrl("https://paywith.pawapay.io/?token=abc")).toBe(true);
      expect(isSafeCheckoutUrl("https://paywith.sandbox.pawapay.io/?token=xyz")).toBe(true);
      expect(isSafeCheckoutUrl("https://api.pawapay.io/checkout/123")).toBe(true);
    });

    it("refuse les protocoles non sécurisés, open-redirects et domaines frauduleux", () => {
      expect(isSafeCheckoutUrl("http://paywith.pawapay.io")).toBe(false);
      expect(isSafeCheckoutUrl("https://pawapay.io.evil.com/pay")).toBe(false);
      expect(isSafeCheckoutUrl("https://fake-pawapay.io/pay")).toBe(false);
      expect(isSafeCheckoutUrl("javascript:alert(1)")).toBe(false);
      expect(isSafeCheckoutUrl("not-a-url")).toBe(false);
    });
  });

  describe("analyse de l'état d'un deposit (readDepositState)", () => {
    it("décode un paiement complété avec succès", () => {
      const state = readDepositState({
        status: "FOUND",
        data: {
          depositId: "8917c345-4791-4285-a416-62f24b6982db",
          status: "COMPLETED",
          amount: "5000",
          currency: "XOF",
          country: "CIV",
          providerTransactionId: "TX123456",
          payer: {
            type: "MMO",
            accountDetails: { phoneNumber: "2250700000000", provider: "MTN_MOMO_CIV" },
          },
        },
      });

      expect(state.paymentStatus).toBe("completed");
      expect(state.amount).toBe(5000);
      expect(state.currency).toBe("XOF");
      expect(state.providerTransactionId).toBe("TX123456");
      expect(state.depositId).toBe("8917c345-4791-4285-a416-62f24b6982db");
    });

    it("décode un paiement en échec ou rejeté", () => {
      const state = readDepositState({
        depositId: "8917c345-4791-4285-a416-62f24b6982db",
        status: "FAILED",
        amount: "5000",
        currency: "XOF",
        failureReason: {
          failureCode: "INSUFFICIENT_FUNDS",
          failureMessage: "Solde insuffisant sur le portefeuille",
        },
      });

      expect(state.paymentStatus).toBe("failed");
      expect(state.failureCode).toBe("INSUFFICIENT_FUNDS");
      expect(state.failureMessage).toBe("Solde insuffisant sur le portefeuille");
    });

    it("décode un paiement en cours d'autorisation", () => {
      const state = readDepositState({
        depositId: "8917c345-4791-4285-a416-62f24b6982db",
        status: "ACCEPTED",
        amount: "4250.00",
        currency: "XOF",
      });

      expect(state.paymentStatus).toBe("pending");
      expect(state.amount).toBe(4250);
    });

    it("décode un statut NOT_FOUND comme paiement en attente d'interaction client", () => {
      const state = readDepositState({ status: "NOT_FOUND" }, "dep-123");
      expect(state.paymentStatus).toBe("pending");
      expect(state.depositId).toBe("dep-123");
    });
  });

  describe("conformité du customerMessage pour pawaPay v2", () => {
    it("nettoie les tirets et caractères spéciaux et garantit la longueur 4-22", () => {
      // Les tirets et underscores sont interdits par l'API
      expect(sanitizeCustomerMessage("CMD-20261116-ABCD")).toBe("CMD20261116ABCD");
      expect(sanitizeCustomerMessage("AB-12")).toBe("AB12");
      // Référence très courte : complétée avec préfixe/suffixe
      expect(sanitizeCustomerMessage("X")).toBe("CMD X");
      // Respect strict de la regex ^[a-zA-Z0-9 ]+$
      expect(/^[a-zA-Z0-9 ]{4,22}$/.test(sanitizeCustomerMessage("CMD-1234"))).toBe(true);
      expect(/^[a-zA-Z0-9 ]{4,22}$/.test(sanitizeCustomerMessage("@#$%"))).toBe(true);
    });
  });

  describe("normalisation de l'URL returnUrl", () => {
    it("préserve une URL distante déjà configurée", () => {
      expect(sanitizeReturnUrl("https://evolyant.com/paiement/retour?commande=123")).toBe(
        "https://evolyant.com/paiement/retour?commande=123",
      );
    });

    it("convertit un nom d'hôte localhost en 127.0.0.1 pour être accepté par l'API pawaPay", () => {
      expect(sanitizeReturnUrl("http://localhost:3000/paiement/retour?commande=abc")).toBe(
        "http://127.0.0.1:3000/paiement/retour?commande=abc",
      );
    });
  });

  describe("conformité du libellé reason pour pawaPay v2", () => {
    it("tronque strictement à 50 caractères maximum sans dépasser", () => {
      const longDesc = "500$ API Claude - 30 days × 1 — commande CMD-3A2PH8VW";
      const sanitized = sanitizeReason(longDesc);
      expect(sanitized).toBeDefined();
      expect(sanitized!.length).toBeLessThanOrEqual(50);
      expect(sanitized).toBe("500$ API Claude - 30 days × 1 — commande CMD-3A2PH");
    });

    it("gère les descriptions vides ou indéfinies", () => {
      expect(sanitizeReason(undefined)).toBeUndefined();
      expect(sanitizeReason(null)).toBeUndefined();
      expect(sanitizeReason("   ")).toBeUndefined();
    });
  });

  describe("détection d'expiration de l'URL CloudFront (isPaymentUrlValid)", () => {
    it("valide une URL avec un timestamp futur", () => {
      const futureUnix = Math.floor(Date.now() / 1000) + 300; // dans 5 minutes
      expect(isPaymentUrlValid(`https://sandbox.paywith.pawapay.io/v2?Expires=${futureUnix}`)).toBe(true);
    });

    it("détecte une URL expirée dans le passé", () => {
      const pastUnix = Math.floor(Date.now() / 1000) - 300; // il y a 5 minutes
      expect(isPaymentUrlValid(`https://sandbox.paywith.pawapay.io/v2?Expires=${pastUnix}`)).toBe(false);
    });

    it("invalide une URL expirant dans moins de 60 secondes", () => {
      const closeUnix = Math.floor(Date.now() / 1000) + 30; // dans 30 secondes
      expect(isPaymentUrlValid(`https://sandbox.paywith.pawapay.io/v2?Expires=${closeUnix}`)).toBe(false);
    });

    it("gère les URLs sans paramètre Expires (ex: mock)", () => {
      expect(isPaymentUrlValid("https://paywith.sandbox.pawapay.io/checkout/abc")).toBe(true);
    });

    it("gère les URLs nulles ou malformées", () => {
      expect(isPaymentUrlValid(null)).toBe(false);
      expect(isPaymentUrlValid(undefined)).toBe(false);
      expect(isPaymentUrlValid("not-a-url")).toBe(false);
    });
  });

  describe("pays pawaPay Mobile Money autorisés (PAWAPAY_XOF_COUNTRIES)", () => {
    it("inclut les pays Mobile Money FCFA (UEMOA et CEMAC)", () => {
      expect(PAWAPAY_XOF_COUNTRIES.has("BFA")).toBe(true);
      expect(PAWAPAY_XOF_COUNTRIES.has("CIV")).toBe(true);
      expect(PAWAPAY_XOF_COUNTRIES.has("BEN")).toBe(true);
      expect(PAWAPAY_XOF_COUNTRIES.has("SEN")).toBe(true);
      expect(PAWAPAY_XOF_COUNTRIES.has("CMR")).toBe(true);
      expect(PAWAPAY_XOF_COUNTRIES.has("COG")).toBe(true);
      expect(PAWAPAY_XOF_COUNTRIES.has("GAB")).toBe(true);
      expect(PAWAPAY_XOF_COUNTRIES.has("USA")).toBe(false);
    });
  });

  describe("résilience de createPaymentPage", () => {
    it("réessaie sans numéro si pawaPay rejette le téléphone pré-saisi", async () => {
      const originalFetch = globalThis.fetch;
      let attempt = 0;
      const calledBodies: unknown[] = [];

      globalThis.fetch = vi.fn().mockImplementation(async (_url, opts) => {
        attempt++;
        const parsed = JSON.parse(opts.body);
        calledBodies.push(parsed);

        if (attempt === 1) {
          // 1ère tentative échoue avec INVALID_PHONE_NUMBER
          return {
            ok: true,
            status: 200,
            json: async () => ({
              failureReason: {
                failureCode: "INVALID_PHONE_NUMBER",
                failureMessage: "The phone number seems to be invalid.",
              },
            }),
          };
        }

        // 2ème tentative (sans phone) réussit
        return {
          ok: true,
          status: 201,
          json: async () => ({
            depositId: parsed.depositId,
            status: "ACCEPTED",
            redirectUrl: "https://paywith.sandbox.pawapay.io/checkout/test-ok",
          }),
        };
      }) as unknown as typeof fetch;

      try {
        const result = await createPaymentPage({
          depositId: "dep-12345",
          orderId: "ord-12345",
          reference: "CMD-TEST",
          amountXof: 2000,
          description: "Test produit",
          customer: {
            email: "client@test.com",
            country: "CI",
            phone: "7000000000",
          },
          returnUrl: "https://example.com/retour",
        });

        expect(result.status).toBe("ACCEPTED");
        expect(result.redirectUrl).toBe("https://paywith.sandbox.pawapay.io/checkout/test-ok");
        expect(calledBodies.length).toBe(2);
        // 1ère tentative avec le numéro formaté
        expect((calledBodies[0] as { phoneNumber?: string }).phoneNumber).toBeDefined();
        // 2ème tentative sans le numéro
        expect((calledBodies[1] as { phoneNumber?: string }).phoneNumber).toBeUndefined();
      } finally {
        globalThis.fetch = originalFetch;
      }
    });

    it("utilise XAF pour les pays de la zone CEMAC (ex: Cameroun, Congo)", async () => {
      const originalFetch = globalThis.fetch;
      const calledBodies: unknown[] = [];

      globalThis.fetch = vi.fn().mockImplementation(async (_url, opts) => {
        const parsed = JSON.parse(opts.body);
        calledBodies.push(parsed);
        return {
          ok: true,
          status: 201,
          json: async () => ({
            depositId: parsed.depositId,
            status: "ACCEPTED",
            redirectUrl: "https://paywith.sandbox.pawapay.io/checkout/test-cmr",
          }),
        };
      }) as unknown as typeof fetch;

      try {
        const result = await createPaymentPage({
          depositId: "dep-cmr",
          orderId: "ord-cmr",
          reference: "CMD-CMR",
          amountXof: 5000,
          description: "Test produit",
          customer: {
            email: "client@test.com",
            country: "CM", // Cameroun (XAF)
          },
          returnUrl: "https://example.com/retour",
        });

        expect(result.status).toBe("ACCEPTED");
        expect(calledBodies.length).toBe(1);
        expect((calledBodies[0] as { country: string }).country).toBe("CMR");
        expect((calledBodies[0] as { amountDetails: { currency: string } }).amountDetails.currency).toBe("XAF");
      } finally {
        globalThis.fetch = originalFetch;
      }
    });

    it("bascule sur BFA pour les pays hors des zones Mobile Money activées", async () => {
      const originalFetch = globalThis.fetch;
      const calledBodies: unknown[] = [];

      globalThis.fetch = vi.fn().mockImplementation(async (_url, opts) => {
        const parsed = JSON.parse(opts.body);
        calledBodies.push(parsed);
        return {
          ok: true,
          status: 201,
          json: async () => ({
            depositId: parsed.depositId,
            status: "ACCEPTED",
            redirectUrl: "https://paywith.sandbox.pawapay.io/checkout/test-bfa",
          }),
        };
      }) as unknown as typeof fetch;

      try {
        const result = await createPaymentPage({
          depositId: "dep-usa",
          orderId: "ord-usa",
          reference: "CMD-USA",
          amountXof: 5000,
          description: "Test produit",
          customer: {
            email: "client@test.com",
            country: "US",
          },
          returnUrl: "https://example.com/retour",
        });

        expect(result.status).toBe("ACCEPTED");
        expect(calledBodies.length).toBe(1);
        expect((calledBodies[0] as { country: string }).country).toBe("BFA");
        expect((calledBodies[0] as { amountDetails: { currency: string } }).amountDetails.currency).toBe("XOF");
      } finally {
        globalThis.fetch = originalFetch;
      }
    });
  });
});
