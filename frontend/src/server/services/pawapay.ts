import "server-only";
import { env } from "@/server/env";

/**
 * Client pawaPay (agrégateur Mobile Money panafricain) — API Marchand v2.
 * Docs : https://docs.pawapay.io/v2/
 *
 * Règles de sécurité fondamentales :
 * 1. Ni le retour du client sur le site, ni le contenu brut d'un webhook ne valent preuve de paiement.
 * 2. Seule la relecture de la transaction (deposit) auprès de l'API pawaPay (avec notre token secret)
 *    fait foi (`getDeposit`).
 * 3. On contrôle strictement l'identifiant (depositId), le montant exact (amountXof) et la devise (XOF).
 * 4. Les redirections client sont bridées au domaine HTTPS officiel de pawaPay (`isSafeCheckoutUrl`).
 */

export class PawaPayError extends Error {
  constructor(
    readonly status: number,
    message: string,
  ) {
    super(message);
    this.name = "PawaPayError";
  }
}

type Obj = Record<string, unknown>;
const obj = (v: unknown): Obj => (v && typeof v === "object" && !Array.isArray(v) ? (v as Obj) : {});
const str = (v: unknown): string => (typeof v === "string" ? v : "");

/** Normalise l'URL de base pour éviter les doubles barres. */
function pawapayBaseUrl(): string {
  const raw = env().PAWAPAY_API_URL.trim().replace(/\/+$/, "");
  return raw;
}

async function call(
  path: string,
  init: { method?: "GET" | "POST"; body?: unknown; idempotencyKey?: string } = {},
): Promise<unknown> {
  const e = env();
  if (!e.PAWAPAY_API_TOKEN) {
    throw new PawaPayError(0, "PAWAPAY_API_TOKEN est absent du fichier .env");
  }

  const base = pawapayBaseUrl();
  const headers: Record<string, string> = {
    authorization: `Bearer ${e.PAWAPAY_API_TOKEN}`,
    accept: "application/json",
  };
  if (init.body !== undefined) {
    headers["content-type"] = "application/json";
  }
  if (init.idempotencyKey) {
    headers["x-idempotency-key"] = init.idempotencyKey;
  }

  let res: Response;
  const endpoint = `${base}${path.startsWith("/") ? path : `/${path}`}`;
  try {
    res = await fetch(endpoint, {
      method: init.method ?? "GET",
      headers,
      body: init.body === undefined ? undefined : JSON.stringify(init.body),
      signal: AbortSignal.timeout(25_000),
      cache: "no-store",
      redirect: "error",
    });
  } catch (error) {
    throw new PawaPayError(0, error instanceof Error ? error.message : "Erreur réseau pawaPay");
  }

  let payload: unknown = null;
  try {
    payload = await res.json();
  } catch {
    // traité ci-dessous si !res.ok
  }

  if (!res.ok) {
    const errorObj = obj(payload);
    const failure = obj(errorObj.failureReason);
    const message =
      (typeof failure.failureMessage === "string" && failure.failureMessage) ||
      (typeof errorObj.message === "string" && errorObj.message) ||
      (typeof errorObj.error === "string" && errorObj.error) ||
      (typeof obj(errorObj.error).message === "string" && obj(errorObj.error).message) ||
      `Erreur pawaPay ${res.status}`;
    throw new PawaPayError(res.status, String(message).slice(0, 300));
  }

  if (payload === null || typeof payload !== "object") {
    throw new PawaPayError(res.status, "Réponse pawaPay illisible");
  }
  return payload;
}

/** Table de correspondance code pays 2 lettres (UEMOA / CEMAC) vers ISO 3166-1 alpha-3 pour pawaPay. */
const ISO2_TO_ISO3: Record<string, string> = {
  BF: "BFA", // Burkina Faso
  BJ: "BEN", // Bénin
  CI: "CIV", // Côte d'Ivoire
  GW: "GNB", // Guinée-Bissau
  ML: "MLI", // Mali
  NE: "NER", // Niger
  SN: "SEN", // Sénégal
  TG: "TGO", // Togo
  CM: "CMR", // Cameroun
  CD: "COD", // RDC
  CG: "COG", // Congo-Brazzaville
  GA: "GAB", // Gabon
  GH: "GHA", // Ghana
  GN: "GIN", // Guinée
  KE: "KEN", // Kenya
  RW: "RWA", // Rwanda
  TZ: "TZA", // Tanzanie
  UG: "UGA", // Ouganda
  ZM: "ZMB", // Zambie
  NG: "NGA", // Nigeria
};

/**
 * Pays pris en charge par pawaPay pour les encaissements Mobile Money en Franc CFA (FCFA).
 * - Zone UEMOA (Burkina Faso, Côte d'Ivoire, Sénégal, Bénin) : devise XOF
 * - Zone CEMAC (Cameroun, Congo-Brazzaville, Gabon) : devise XAF (1 XAF = 1 XOF = 1 FCFA)
 *
 * Si un client choisit un pays en dehors de ces zones, nous basculons automatiquement
 * sur BFA afin d'ouvrir la page de paiement Mobile Money sans blocage.
 */
export const PAWAPAY_MOBILE_MONEY_CONFIG: Record<string, { currency: "XOF" | "XAF"; name: string }> = {
  BFA: { currency: "XOF", name: "Burkina Faso" },
  CIV: { currency: "XOF", name: "Côte d'Ivoire" },
  SEN: { currency: "XOF", name: "Sénégal" },
  BEN: { currency: "XOF", name: "Bénin" },
  CMR: { currency: "XAF", name: "Cameroun" },
  COG: { currency: "XAF", name: "Congo" },
  GAB: { currency: "XAF", name: "Gabon" },
};

export const PAWAPAY_XOF_COUNTRIES = new Set(Object.keys(PAWAPAY_MOBILE_MONEY_CONFIG));

export function toIso3Country(code?: string | null): string | undefined {
  if (!code) return undefined;
  const clean = code.trim().toUpperCase();
  if (clean.length === 3) return clean;
  return ISO2_TO_ISO3[clean] ?? undefined;
}

const COUNTRY_DIALING_CODES: Record<string, string> = {
  BF: "226",
  BFA: "226",
  BJ: "229",
  BEN: "229",
  CI: "225",
  CIV: "225",
  GW: "245",
  GNB: "245",
  ML: "223",
  MLI: "223",
  NE: "227",
  NER: "227",
  SN: "221",
  SEN: "221",
  TG: "228",
  TGO: "228",
  CM: "237",
  CMR: "237",
  CD: "243",
  COD: "243",
  CG: "242",
  COG: "242",
  GA: "241",
  GAB: "241",
  GH: "233",
  GHA: "233",
  GN: "224",
  GIN: "224",
  KE: "254",
  KEN: "254",
  RW: "250",
  RWA: "250",
  TZ: "255",
  TZA: "255",
  UG: "256",
  UGA: "256",
  ZM: "260",
  ZMB: "260",
  NG: "234",
  NGA: "234",
};

/**
 * Nettoie et formate un numéro de téléphone pour pawaPay (chiffres uniquement, sans `+`).
 * Si le numéro est saisi sans indicatif pays (ex: 70001122), préfixe automatiquement
 * avec l'indicatif correspondant au pays (ex: 22670001122).
 */
export function formatPhoneForPawaPay(phone?: string | null, countryCode?: string | null): string | undefined {
  if (!phone) return undefined;
  let digits = phone.replace(/^00/, "").replace(/[^0-9]/g, "");
  if (!digits) return undefined;

  const countryKey = (countryCode || "").trim().toUpperCase();
  const dialingCode = COUNTRY_DIALING_CODES[countryKey] || (countryKey === "" ? "226" : undefined);

  if (dialingCode && !digits.startsWith(dialingCode) && digits.length <= 10) {
    digits = `${dialingCode}${digits}`;
  }

  return digits.length >= 8 && digits.length <= 15 ? digits : undefined;
}

/**
 * Sécurise le message client pour pawaPay v2 :
 * Exigence stricte de l'API : ^[a-zA-Z0-9 ]+$ et longueur entre 4 et 22 caractères.
 * Les caractères de ponctuation (dont les tirets et underscores) sont rejetés avec INVALID_PARAMETER.
 */
export function sanitizeCustomerMessage(reference: string): string {
  const rawClean = reference.replace(/[^A-Za-z0-9 ]/g, "").trim();
  return (rawClean.length >= 4 ? rawClean : `CMD ${rawClean}`.padEnd(4, "X")).slice(0, 22);
}

/**
 * Sécurise le libellé « reason » pour pawaPay v2 :
 * Exigence stricte de l'API : chaîne de 50 caractères maximum.
 * Au-delà de 50 caractères, pawaPay rejette avec :
 * « Value for parameter ‘reason’ is invalid. Please consult API documentation for validation rules. »
 */
export function sanitizeReason(description?: string | null): string | undefined {
  if (!description) return undefined;
  const clean = description.replace(/[\r\n\t]+/g, " ").trim();
  if (!clean) return undefined;
  return clean.slice(0, 50).trim();
}

/**
 * Normalise l'URL de retour pour pawaPay v2 :
 * - pawaPay rejette le nom d'hôte « localhost » (INVALID_PARAMETER), mais accepte « 127.0.0.1 ».
 * - En développement local, on remplace donc « localhost » par « 127.0.0.1 » pour que le navigateur
 *   du client revienne directement sur son serveur local sans être redirigé vers 404 sur un domaine externe.
 */
export function sanitizeReturnUrl(returnUrl: string): string {
  try {
    const u = new URL(returnUrl);
    if (u.hostname === "localhost") {
      u.hostname = "127.0.0.1";
      return u.toString();
    }
    return u.toString();
  } catch {
    return returnUrl;
  }
}

export type CreatePaymentPageInput = {
  depositId: string;
  orderId: string;
  reference: string;
  amountXof: number;
  description: string;
  customer: {
    email: string;
    firstName?: string;
    lastName?: string;
    country?: string;
    phone?: string;
  };
  returnUrl: string;
  lang?: "fr" | "en";
};

export type PaymentPageState = {
  depositId: string;
  status: string;
  redirectUrl: string;
};

/**
 * Initialise une session de paiement hébergée (Hosted Payment Page) via pawaPay v2.
 * pawaPay prend en charge la détection opérateur, la sélection mobile money et l'USSD/OTP.
 *
 * Résilience multi-niveaux :
 * 1. Prise en charge des deux zones Franc CFA (UEMOA en XOF et CEMAC en XAF à parité 1:1).
 *    Si le pays sélectionné est hors des zones activées, bascule automatique sur BFA pour ouvrir
 *    la passerelle Mobile Money sans blocage.
 * 2. Si le numéro de téléphone saisi est rejeté par pawaPay (INVALID_PHONE_NUMBER ou opérateur non configuré),
 *    on réessaie immédiatement sans le champ phoneNumber afin que pawaPay affiche la sélection d'opérateur
 *    Mobile Money directement sur sa page hébergée sécurisée.
 */
export async function createPaymentPage(input: CreatePaymentPageInput): Promise<PaymentPageState> {
  const iso3 = toIso3Country(input.customer.country);
  const config = (iso3 && PAWAPAY_MOBILE_MONEY_CONFIG[iso3]) || PAWAPAY_MOBILE_MONEY_CONFIG.BFA;
  const preferredCountry = iso3 && PAWAPAY_MOBILE_MONEY_CONFIG[iso3] ? iso3 : "BFA";
  const currency = config.currency;
  const phone = formatPhoneForPawaPay(input.customer.phone, preferredCountry);
  const customerMessage = sanitizeCustomerMessage(input.reference);
  const returnUrl = sanitizeReturnUrl(input.returnUrl);
  const reason = sanitizeReason(input.description);

  const baseMetadata = [
    { orderId: input.orderId },
    { reference: input.reference },
    { customerEmail: input.customer.email },
  ];

  const requestSession = async (
    country: string,
    sessionCurrency: "XOF" | "XAF",
    phoneNumber?: string,
    attempt = 1,
  ): Promise<PaymentPageState> => {
    const body: Record<string, unknown> = {
      depositId: input.depositId,
      returnUrl,
      customerMessage,
      amountDetails: {
        amount: String(input.amountXof),
        currency: sessionCurrency,
      },
      language: input.lang === "en" ? "EN" : "FR",
      country,
      metadata: baseMetadata,
    };

    if (reason) body.reason = reason;
    if (phoneNumber) body.phoneNumber = phoneNumber;

    const payload = await call("/paymentpage", {
      method: "POST",
      idempotencyKey: `paymentpage-${input.depositId}-${attempt}`,
      body,
    });

    const resObj = obj(payload);
    const failure = obj(resObj.failureReason);
    if (typeof failure.failureMessage === "string" && failure.failureMessage) {
      throw new PawaPayError(200, failure.failureMessage);
    }

    const depositId = str(resObj.depositId) || input.depositId;
    const status = str(resObj.status) || "ACCEPTED";
    const redirectUrl = str(resObj.redirectUrl);

    if (!redirectUrl || !isSafeCheckoutUrl(redirectUrl)) {
      throw new PawaPayError(200, "URL de redirection pawaPay invalide ou absente");
    }

    return { depositId, status, redirectUrl };
  };

  // 1ère tentative : avec le pays demandé, sa devise FCFA (XOF/XAF) et le numéro pré-saisi
  try {
    return await requestSession(preferredCountry, currency, phone, 1);
  } catch (err1) {
    // Si l'échec est lié au numéro (opérateur non supporté, longueur ou format rejeté),
    // on relance sans le numéro pour que pawaPay affiche la sélection d'opérateur Mobile Money
    if (phone) {
      try {
        return await requestSession(preferredCountry, currency, undefined, 2);
      } catch (err2) {
        if (preferredCountry !== "BFA") {
          return await requestSession("BFA", "XOF", undefined, 3);
        }
        throw err2;
      }
    }

    // Sans numéro, si le pays initial a échoué (ex: hors zone)
    if (preferredCountry !== "BFA") {
      return await requestSession("BFA", "XOF", undefined, 3);
    }

    throw err1;
  }
}

export type DepositPaymentStatus = "pending" | "completed" | "failed" | "expired" | "unknown";

export type DepositState = {
  depositId: string;
  status: string;
  paymentStatus: DepositPaymentStatus;
  amount: number | null;
  currency: string;
  country: string;
  providerTransactionId: string;
  failureCode?: string;
  failureMessage?: string;
};

/**
 * Relit l'état d'un deposit (paiement reçu) directement auprès de l'API pawaPay v2.
 * Source de vérité absolue pour confirmer l'encaissement d'une commande.
 */
export async function getDeposit(depositId: string): Promise<DepositState> {
  const payload = await call(`/deposits/${encodeURIComponent(depositId)}`);
  return readDepositState(payload, depositId);
}

export function readDepositState(payload: unknown, fallbackDepositId = ""): DepositState {
  const root = obj(payload);
  // Dans pawaPay v2, GET /v2/deposits/{id} renvoie { status: "FOUND", data: { ... } }
  // Les webhooks envoient directement l'objet { depositId, status, amount, ... }
  const rawData = root.data !== undefined ? root.data : root;
  const data = Array.isArray(rawData) ? obj(rawData[0]) : obj(rawData);

  const rawStatus = (str(data.status) || str(root.status)).toUpperCase();
  const depId = str(data.depositId) || str(root.depositId) || fallbackDepositId;

  let paymentStatus: DepositPaymentStatus = "unknown";
  if (rawStatus === "COMPLETED") {
    paymentStatus = "completed";
  } else if (rawStatus === "EXPIRED") {
    paymentStatus = "expired";
  } else if (rawStatus === "FAILED" || rawStatus === "REJECTED") {
    paymentStatus = "failed";
  } else if (
    rawStatus === "ACCEPTED" ||
    rawStatus === "SUBMITTED" ||
    rawStatus === "PENDING" ||
    rawStatus === "PROCESSING" ||
    rawStatus === "FOUND" ||
    rawStatus === "NOT_FOUND"
  ) {
    paymentStatus = "pending";
  }

  const rawAmount = data.amount;
  let amountNum: number | null = null;
  if (typeof rawAmount === "number" && Number.isFinite(rawAmount)) {
    amountNum = rawAmount;
  } else if (typeof rawAmount === "string" && rawAmount.trim() !== "") {
    const parsed = Number(rawAmount);
    if (Number.isFinite(parsed)) amountNum = parsed;
  }

  const currency = str(data.currency).toUpperCase();
  const country = str(data.country).toUpperCase();
  const providerTransactionId = str(data.providerTransactionId);

  const failure = obj(data.failureReason);
  const failureCode = str(failure.failureCode) || (paymentStatus === "failed" ? rawStatus : undefined);
  const failureMessage = str(failure.failureMessage) || undefined;

  return {
    depositId: depId,
    status: rawStatus,
    paymentStatus,
    amount: amountNum,
    currency,
    country,
    providerTransactionId,
    failureCode,
    failureMessage,
  };
}

/**
 * Sécurité anti-hameçonnage et open-redirect :
 * On n'autorise que les redirections vers un domaine officiel HTTPS de pawaPay (*.pawapay.io).
 * En développement local, autorise aussi localhost/127.0.0.1 pour le serveur de mock.
 */
export function isSafeCheckoutUrl(url: string): boolean {
  try {
    const u = new URL(url);
    if (u.protocol !== "https:") {
      if (process.env.NODE_ENV === "development" && (u.hostname === "127.0.0.1" || u.hostname === "localhost")) {
        return true;
      }
      return false;
    }
    return u.hostname === "pawapay.io" || u.hostname.endsWith(".pawapay.io");
  } catch {
    return false;
  }
}

/**
 * Vérifie si une URL de paiement signée pawaPay (AWS CloudFront) est encore valide.
 * Les URLs CloudFront portent un paramètre `Expires` (timestamp Unix en secondes).
 * Si `Expires` est dépassé ou va expirer dans les 60 prochaines secondes, l'URL n'est plus valide.
 */
export function isPaymentUrlValid(url?: string | null): boolean {
  if (!url) return false;
  try {
    const u = new URL(url);
    const expiresParam = u.searchParams.get("Expires");
    if (!expiresParam) return true; // Pas d'expiration spécifiée (ex. mock en test)
    const expires = parseInt(expiresParam, 10);
    if (isNaN(expires)) return false;
    const nowSec = Math.floor(Date.now() / 1000);
    // On conserve une marge de sécurité de 60 secondes
    return expires - nowSec > 60;
  } catch {
    return false;
  }
}
