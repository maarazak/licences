// Affiche des secrets neufs à copier dans .env (ne modifie aucun fichier).
import { randomBytes } from "node:crypto";

console.log(`AUTH_SECRET="${randomBytes(48).toString("base64url")}"`);
console.log(`ENCRYPTION_KEY="${randomBytes(32).toString("base64")}"`);
console.log(`CRON_SECRET="${randomBytes(24).toString("hex")}"`);
