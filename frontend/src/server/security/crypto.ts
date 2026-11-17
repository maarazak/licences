import "server-only";
import { createCipheriv, createDecipheriv, createHash, createHmac, randomBytes, randomInt, randomUUID, timingSafeEqual } from "node:crypto";
import { env } from "@/server/env";

export function newId(): string {
  return randomUUID();
}

export function sha256(value: string): string {
  return createHash("sha256").update(value).digest("hex");
}

/** HMAC-SHA256 avec le secret applicatif ; `purpose` cloisonne les usages. */
export function hmac(purpose: string, value: string): string {
  return createHmac("sha256", env().AUTH_SECRET).update(`${purpose}:${value}`).digest("hex");
}

export function safeEqual(a: string, b: string): boolean {
  const ab = Buffer.from(a);
  const bb = Buffer.from(b);
  if (ab.length !== bb.length) return false;
  return timingSafeEqual(ab, bb);
}

export function randomToken(bytes = 32): string {
  return randomBytes(bytes).toString("base64url");
}

/** Code numérique à 6 chiffres, tiré d'une source cryptographique. */
export function randomCode(): string {
  return String(randomInt(0, 1_000_000)).padStart(6, "0");
}

const REF_ALPHABET = "23456789ABCDEFGHJKMNPQRSTVWXYZ"; // sans 0/O/1/I/L/U

export function randomReference(prefix: string, length = 8): string {
  let out = "";
  for (let i = 0; i < length; i++) out += REF_ALPHABET[randomInt(0, REF_ALPHABET.length)];
  return `${prefix}-${out}`;
}

function key(): Buffer {
  return Buffer.from(env().ENCRYPTION_KEY, "base64");
}

/**
 * Chiffrement authentifié AES-256-GCM. Format v2 : v2.iv.tag.données (base64url), avec des données associées
 * (`aad`, ex. l'identifiant de la commande) : un contenu chiffré copié vers une autre commande ne se déchiffre pas.
 * Le format v1 (sans données associées) reste lisible.
 */
export function encrypt(plain: string, aad: string): string {
  const iv = randomBytes(12);
  const cipher = createCipheriv("aes-256-gcm", key(), iv);
  cipher.setAAD(Buffer.from(aad));
  const data = Buffer.concat([cipher.update(plain, "utf8"), cipher.final()]);
  const tag = cipher.getAuthTag();
  return ["v2", iv.toString("base64url"), tag.toString("base64url"), data.toString("base64url")].join(".");
}

export function decrypt(payload: string, aad: string): string {
  const [version, iv, tag, data] = payload.split(".");
  if ((version !== "v1" && version !== "v2") || !iv || !tag || data === undefined) throw new Error("Format chiffré inconnu");
  const decipher = createDecipheriv("aes-256-gcm", key(), Buffer.from(iv, "base64url"));
  if (version === "v2") decipher.setAAD(Buffer.from(aad));
  decipher.setAuthTag(Buffer.from(tag, "base64url"));
  return Buffer.concat([decipher.update(Buffer.from(data, "base64url")), decipher.final()]).toString("utf8");
}
