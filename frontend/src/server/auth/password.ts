import "server-only";
import { randomBytes, scrypt, timingSafeEqual } from "node:crypto";

/**
 * Mots de passe des administrateurs : scrypt (N=16384, r=8, p=1), sel aléatoire de 16 octets.
 * Format stocké : scrypt$N$r$p$sel$hash (base64url). Le même format est produit par scripts/admin-password.mjs.
 */
const N = 16384;
const R = 8;
const P = 1;
const KEYLEN = 64;

function derive(password: string, salt: Buffer, n: number, r: number, p: number): Promise<Buffer> {
  return new Promise((resolve, reject) => scrypt(password, salt, KEYLEN, { N: n, r, p, maxmem: 64 * 1024 * 1024 }, (err, key) => (err ? reject(err) : resolve(key))));
}

export async function hashPassword(password: string): Promise<string> {
  const salt = randomBytes(16);
  const key = await derive(password, salt, N, R, P);
  return ["scrypt", N, R, P, salt.toString("base64url"), key.toString("base64url")].join("$");
}

// Empreinte factice : un compte inconnu coûte le même temps de calcul qu'un compte réel (pas d'énumération).
const DUMMY = `scrypt$${N}$${R}$${P}$${Buffer.alloc(16).toString("base64url")}$${Buffer.alloc(KEYLEN).toString("base64url")}`;

export async function verifyPassword(password: string, stored: string | null | undefined): Promise<boolean> {
  const parts = (stored || DUMMY).split("$");
  const [scheme, n, r, p, salt, hash] = parts;
  if (parts.length !== 6 || scheme !== "scrypt" || !salt || !hash) return false;
  const expected = Buffer.from(hash, "base64url");
  const actual = await derive(password, Buffer.from(salt, "base64url"), Number(n), Number(r), Number(p));
  return stored ? expected.length === actual.length && timingSafeEqual(expected, actual) : false;
}
