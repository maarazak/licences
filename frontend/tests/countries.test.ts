import { describe, expect, it } from "vitest";
import { COUNTRIES, getCountry, validatePhoneForCountry } from "@/lib/countries";

describe("système de pays et validation téléphonique Mobile Money", () => {
  it("trouve les informations du pays par défaut (Burkina Faso)", () => {
    const bf = getCountry("BF");
    expect(bf.code).toBe("BF");
    expect(bf.dial).toBe("+226");
    expect(bf.minDigits).toBe(8);
    expect(bf.maxDigits).toBe(8);
  });

  it("valide la longueur exacte pour le Burkina Faso (8 chiffres)", () => {
    expect(validatePhoneForCountry("70000000", "BF").valid).toBe(true);
    expect(validatePhoneForCountry("70 00 00 00", "BF").valid).toBe(true);
    expect(validatePhoneForCountry("+226 70 00 00 00", "BF").valid).toBe(true);
    expect(validatePhoneForCountry("7000000", "BF").valid).toBe(false); // 7 chiffres
    expect(validatePhoneForCountry("700000009", "BF").valid).toBe(false); // 9 chiffres
  });

  it("valide la longueur exacte pour la Côte d'Ivoire (10 chiffres)", () => {
    expect(validatePhoneForCountry("0700112233", "CI").valid).toBe(true);
    expect(validatePhoneForCountry("+225 07 00 11 22 33", "CI").valid).toBe(true);
    expect(validatePhoneForCountry("07001122", "CI").valid).toBe(false); // 8 chiffres ancien format
  });

  it("valide la longueur exacte pour le Sénégal (9 chiffres)", () => {
    expect(validatePhoneForCountry("771234567", "SN").valid).toBe(true);
    expect(validatePhoneForCountry("77 123 45 67", "SN").valid).toBe(true);
    expect(validatePhoneForCountry("77123456", "SN").valid).toBe(false); // 8 chiffres
  });

  it("valide la plage autorisée pour le Bénin (8 à 10 chiffres)", () => {
    expect(validatePhoneForCountry("90123456", "BJ").valid).toBe(true); // 8 chiffres
    expect(validatePhoneForCountry("9012345678", "BJ").valid).toBe(true); // 10 chiffres
    expect(validatePhoneForCountry("901234", "BJ").valid).toBe(false); // trop court
  });

  it("contient tous les pays majeurs pris en charge par pawaPay", () => {
    const codes = COUNTRIES.map((c) => c.code);
    expect(codes).toContain("BF");
    expect(codes).toContain("CI");
    expect(codes).toContain("SN");
    expect(codes).toContain("ML");
    expect(codes).toContain("CM");
    expect(codes).toContain("CD");
    expect(codes).toContain("GN");
    expect(codes).toContain("XX"); // Option universelle
  });

  it("gère l'option universelle 'Autre pays' avec la norme internationale (6 à 15 chiffres)", () => {
    const other = getCountry("XX");
    expect(other.name).toBe("Autre pays");
    expect(other.flag).toBe("🌍");
    expect(other.minDigits).toBe(6);
    expect(other.maxDigits).toBe(15);

    expect(validatePhoneForCountry("+33612345678", "XX").valid).toBe(true);
    expect(validatePhoneForCountry("12345", "XX").valid).toBe(false); // trop court (< 6)
  });

  it("gère gracieusement n'importe quel code inconnu sans planter", () => {
    const unknown = getCountry("UNKNOWN_CODE");
    expect(unknown.name).toBe("Autre pays");
    expect(validatePhoneForCountry("1234567890", "UNKNOWN_CODE").valid).toBe(true);
  });
});
