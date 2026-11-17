import { describe, expect, it } from "vitest";
import { deliveryKindLabel } from "../src/lib/enums";
import { formatDuration, formatWarranty, formatXof } from "../src/lib/format";

const norm = (s: string) => s.replace(/[  ]/g, " ");

describe("format", () => {
  it("formate les montants FCFA", () => {
    expect(norm(formatXof(2250))).toBe("2 250 FCFA");
  });
  it("formate les durées", () => {
    expect(formatDuration(30)).toBe("1 mois");
    expect(formatDuration(180)).toBe("6 mois");
    expect(formatDuration(365)).toBe("1 an");
    expect(formatDuration(730)).toBe("2 ans");
    expect(formatDuration(7)).toBe("7 jours");
    expect(formatDuration(0)).toBe("Selon l'offre");
  });
  it("traduit la garantie fournisseur", () => {
    expect(formatWarranty("30 days")).toBe("30 jours");
    expect(formatWarranty("1 day")).toBe("1 jour");
    expect(formatWarranty("")).toBe("Aucune garantie annoncée");
    expect(formatWarranty("Full warranty")).toBe("Garantie sur toute la durée");
    expect(formatWarranty("No Warranty")).toBe("Aucune garantie");
    expect(formatWarranty("3 Months Warranty")).toBe("3 mois");
    expect(formatWarranty("1 Days Warranty")).toBe("1 jour");
    expect(formatWarranty("24 Hours")).toBe("24 heures");
    expect(formatWarranty("2 years")).toBe("2 ans");
    expect(formatWarranty("W25D")).toBe("25 jours");
    expect(formatWarranty("25")).toBe("25 jours");
    expect(formatWarranty("Remplacement sous 48 h")).toBe("Remplacement sous 48 h");
  });
  it("libelle le mode de livraison", () => {
    expect(deliveryKindLabel("activation_link")).toBe("Lien d'activation");
    expect(deliveryKindLabel("account")).toBe("Compte fourni");
    expect(deliveryKindLabel("accounts")).toBe("Compte fourni");
    expect(deliveryKindLabel("link")).toBe("Lien d'activation");
    expect(deliveryKindLabel("code")).toBe("Code à copier");
    expect(deliveryKindLabel("")).toBe("Accès numérique");
  });
});
