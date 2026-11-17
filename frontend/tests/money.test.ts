import { describe, expect, it } from "vitest";
import { formatMoney } from "../src/lib/money";

describe("conversion FCFA / dollar", () => {
  const norm = (s: string) => s.replace(/[  ]/g, " ");
  it("formate en FCFA par défaut et sans taux", () => {
    expect(norm(formatMoney(2250, "XOF", 600, "fr"))).toBe("2 250 FCFA");
    expect(norm(formatMoney(2250, "USD", null, "fr"))).toBe("2 250 FCFA");
  });
  it("convertit en dollars selon la langue", () => {
    expect(formatMoney(6000, "USD", 600, "en")).toBe("$10.00");
    expect(norm(formatMoney(6000, "USD", 600, "fr"))).toBe("10,00 $");
    expect(formatMoney(2250, "USD", 600, "en")).toBe("$3.75");
  });
});
