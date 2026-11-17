// Vérification automatique des secrets avant chaque commit (hook git `.githooks/pre-commit`).
//   node scripts/check-secrets.mjs          analyse les lignes AJOUTÉES dans l'index git (ce qui va être commité)
//   node scripts/check-secrets.mjs --all    analyse tous les fichiers suivis par git
// Bloque le commit (code de sortie 1) si une clé, un mot de passe ou un fichier secret est détecté.
// Ne jamais afficher la valeur détectée : seulement le fichier, la ligne et le type.
// Exception volontaire sur une ligne : ajouter `secret-scan: ignore` en commentaire.
import { execFileSync } from "node:child_process";
import { existsSync, readFileSync, statSync } from "node:fs";
import { pathToFileURL } from "node:url";

/** Fichiers qui ne doivent jamais être commités (le gabarit `.env.example` est autorisé). */
export function isBlockedFile(path) {
  const name = path.split("/").pop() ?? path;
  if (name === ".env.example") return false;
  return /^\.env(\..+)?$/.test(name) || /\.(pem|key|p12|pfx|keystore|jks)$/i.test(name) || /^id_(rsa|dsa|ecdsa|ed25519)$/.test(name);
}

const PLACEHOLDER = /(<[^>]+>|x{4,}|\*{3,}|\.{3}|exemple|example|changeme|change_me|votre|your[_-]|placeholder|motdepasse|password$|mock|test|fake|dummy|secret$)/i;

/** Formats de clés connus (préfixes propres à chaque service). */
const RULES = [
  ["clé API revendeur (rk_live_)", /\brk_live_[A-Za-z0-9]{16,}/],
  ["clé secrète live (sk_live_)", /\bsk_live_[A-Za-z0-9]{16,}/],
  ["clé SMTP/API Brevo", /\bx(smtp|key)sib-[A-Za-z0-9-]{20,}/],
  ["clé d'accès AWS", /\bAKIA[0-9A-Z]{16}\b/],
  ["jeton GitHub", /\b(gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,})/],
  ["clé API Google", /\bAIza[0-9A-Za-z_-]{35}\b/],
  ["jeton Slack", /\bxox[baprs]-[A-Za-z0-9-]{10,}/],
  ["clé privée", /-----BEGIN [A-Z ]*PRIVATE KEY-----/],
];

/** Adresse de base de données avec mot de passe en clair (mot de passe vide ou de démonstration toléré). */
const DB_URL = /\b(?:mysql|postgres(?:ql)?|mongodb(?:\+srv)?|redis):\/\/[^\s:@/]+:([^\s@/]{4,})@/i;

/** Affectation d'un secret : NOM_SECRET = "valeur longue". */
const ASSIGN = /\b[A-Za-z0-9_]*(?:SECRET|PASSWORD|PASSWD|TOKEN|API_?KEY|PRIVATE_?KEY|ENCRYPTION_?KEY)[A-Za-z0-9_]*["']?\s*[:=]\s*["']?([A-Za-z0-9+/_=.-]{20,})["']?/i;

/** Valeurs réelles du .env local : leur simple présence dans un commit est une fuite. */
export function loadEnvSecrets(file = ".env") {
  if (!existsSync(file)) return [];
  const secrets = [];
  for (const line of readFileSync(file, "utf8").split("\n")) {
    const m = /^\s*([A-Z0-9_]*(?:SECRET|KEY|PASSWORD|PASS|TOKEN)[A-Z0-9_]*)\s*=\s*(.*)$/.exec(line);
    if (!m) continue;
    const value = m[2].trim().replace(/^["']|["']$/g, "");
    if (value.length >= 12 && !PLACEHOLDER.test(value)) secrets.push({ name: m[1], value });
  }
  return secrets;
}

/** Écarte les valeurs qui n'ont pas l'air d'un secret : gabarits, UUID, chemins/identifiants à points, suites répétitives. */
function looksLikeSecret(value) {
  if (PLACEHOLDER.test(value)) return false;
  if (/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value)) return false;
  if (/^[A-Za-z]+(\.[A-Za-z]+)+$/.test(value)) return false;
  return new Set(value).size >= 8;
}

/** Analyse une ligne ; retourne les types de secrets détectés (jamais leur valeur). */
export function scanLine(line, envSecrets = []) {
  if (/secret-scan:\s*ignore/.test(line)) return [];
  const found = [];
  for (const [label, re] of RULES) if (re.test(line)) found.push(label);
  const db = DB_URL.exec(line);
  if (db && !PLACEHOLDER.test(db[1])) found.push("mot de passe de base de données dans une adresse");
  const assign = ASSIGN.exec(line);
  if (assign && looksLikeSecret(assign[1])) found.push("secret affecté en clair");
  for (const { name, value } of envSecrets) if (line.includes(value)) found.push(`valeur de ${name} (fichier .env local)`);
  return [...new Set(found)];
}

const git = (args) => execFileSync("git", args, { encoding: "utf8", maxBuffer: 256 * 1024 * 1024 });

function addedLines(file) {
  const out = git(["diff", "--cached", "-U0", "--no-color", "--", file]);
  const lines = [];
  let n = 0;
  for (const l of out.split("\n")) {
    const hunk = /^@@ -\d+(?:,\d+)? \+(\d+)/.exec(l);
    if (hunk) { n = Number(hunk[1]); continue; }
    if (l.startsWith("+++") || l.startsWith("---")) continue;
    if (l.startsWith("+")) { lines.push([n++, l.slice(1)]); }
  }
  return lines;
}

function wholeFileLines(file) {
  if (!existsSync(file) || statSync(file).size > 1_000_000) return [];
  const text = readFileSync(file, "utf8");
  if (text.includes("\u0000")) return [];
  return text.split("\n").map((l, i) => [i + 1, l]);
}

function main() {
  const all = process.argv.includes("--all");
  const files = (all ? git(["ls-files", "-z"]) : git(["diff", "--cached", "--name-only", "--diff-filter=ACMR", "-z"])).split("\0").filter(Boolean);
  const envSecrets = loadEnvSecrets();
  const problems = [];
  for (const file of files) {
    if (isBlockedFile(file)) { problems.push(`${file} : fichier secret (ne doit jamais être commité)`); continue; }
    if (/(^|\/)(package-lock\.json|i18n-en\.ts)$/.test(file)) continue;
    for (const [n, line] of all ? wholeFileLines(file) : addedLines(file)) {
      for (const label of scanLine(line, envSecrets)) problems.push(`${file}:${n} : ${label}`);
    }
  }
  if (!problems.length) { console.log(`Vérification des secrets : rien à signaler (${files.length} fichier(s)).`); return; }
  console.error("\n✖ COMMIT BLOQUÉ : secret détecté\n");
  for (const p of problems) console.error(`  - ${p}`);
  console.error("\nRetirez le secret du fichier (utilisez le .env, ignoré par git), puis refaites `git add` et le commit.");
  console.error("Si c'est un faux positif sur une ligne précise, ajoutez le commentaire `secret-scan: ignore` sur cette ligne.");
  console.error("Si un vrai secret a déjà été commité ou poussé, renouvelez-le chez le fournisseur : le retirer de git ne suffit pas.\n");
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) main();
