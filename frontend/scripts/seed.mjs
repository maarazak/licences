// Exécute les seeders de database/seeders (comme `php artisan db:seed`).
//   npm run db:seed              tous les seeders, dans l'ordre alphabétique
//   npm run db:seed -- admin     un seul seeder (nom du fichier sans extension)
import { readdir } from "node:fs/promises";
import { pathToFileURL } from "node:url";
import path from "node:path";
import mysql from "mysql2/promise";

/** Saisie dans le terminal ; `hidden` masque les caractères (mots de passe). */
export function ask(question, hidden = false) {
  return new Promise((resolve) => {
    const stdin = process.stdin;
    process.stdout.write(question);
    let value = "";
    stdin.setRawMode?.(hidden);
    stdin.resume();
    stdin.setEncoding("utf8");
    const onData = (chunk) => {
      for (const ch of chunk) {
        if (ch === "\u0003") process.exit(130);
        if (ch === "\r" || ch === "\n") {
          stdin.setRawMode?.(false);
          stdin.pause();
          stdin.off("data", onData);
          process.stdout.write("\n");
          return resolve(value);
        }
        if (ch === "\u007f" || ch === "\b") value = value.slice(0, -1);
        else {
          value += ch;
          if (!hidden) process.stdout.write(ch);
        }
      }
    };
    stdin.on("data", onData);
  });
}

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  const dir = path.resolve("database/seeders");
  const only = process.argv[2];
  const files = (await readdir(dir)).filter((f) => f.endsWith(".mjs")).sort();
  const selected = only ? files.filter((f) => f.replace(/\.mjs$/, "") === only) : files;
  if (!selected.length) { console.error(`Seeder introuvable : ${only}. Disponibles : ${files.map((f) => f.replace(/\.mjs$/, "")).join(", ")}`); process.exit(1); }
  const db = await mysql.createConnection({ uri: process.env.DATABASE_URL, timezone: "Z" });
  try {
    for (const file of selected) {
      const seeder = (await import(pathToFileURL(path.join(dir, file)).href)).default;
      if (seeder.interactive && !process.stdin.isTTY) { console.log(`- ${file} ignoré (terminal interactif requis)`); continue; }
      console.log(`> ${file}`);
      await seeder.run({ db, ask });
    }
  } finally {
    await db.end();
  }
}
