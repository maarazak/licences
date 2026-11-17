import { readdirSync, readFileSync, statSync } from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";
import { EN, EN_PATTERNS } from "../src/lib/i18n-en";
import { makeT } from "../src/lib/i18n";

function sources(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const full = path.join(dir, name);
    if (statSync(full).isDirectory()) return name === "admin" ? [] : sources(full);
    return /\.(ts|tsx)$/.test(name) && !name.startsWith("i18n-en") ? [full] : [];
  });
}

const t = makeT(EN, EN_PATTERNS);

describe("traduction anglaise", () => {
  it("chaque texte passé à t() existe dans le dictionnaire", () => {
    const missing: string[] = [];
    for (const file of sources("src")) {
      const code = readFileSync(file, "utf8");
      for (const m of code.matchAll(/\bt\(\s*"((?:[^"\\\n]|\\.)*)"/g)) {
        const key = m[1].replace(/\\"/g, '"').replace(/\\'/g, "'");
        const known = key in EN || EN_PATTERNS.some(([source]) => new RegExp(`^${source}$`).test(key));
        if (!known && !/^[\d\s.,:;!?€%()/+-]*$/.test(key)) {
          missing.push(`${path.relative(".", file)} : ${key}`);
        }
      }
    }
    expect(missing).toEqual([]);
  });

  it("les variables du français se retrouvent dans l'anglais", () => {
    const broken = Object.entries(EN).filter(([fr, en]) => [...fr.matchAll(/\{(\w+)\}/g)].some(([v]) => !en.includes(v)));
    expect(broken.map(([fr]) => fr)).toEqual([]);
  });

  it("traduit les durées et les messages à valeurs", () => {
    expect(t("1 mois")).toBe("1 month");
    expect(t("6 mois")).toBe("6 months");
    expect(t("2 ans")).toBe("2 years");
    expect(t("30 jours")).toBe("30 days");
    expect(t("Trop de tentatives. Réessayez dans 5 minutes.")).toBe("Too many attempts. Try again in 5 minutes.");
    expect(t("Plus que {n}", { n: 3 })).toBe("Only 3 left");
  });
});
