/**
 * Liste des pays pris en charge pour les paiements (CinetPay / Cartes bancaires / UEMOA / CEMAC).
 * Inclut l'indicatif international, le drapeau emoji, le nombre de chiffres attendus
 * et les fuseaux horaires pour la détection automatique locale.
 */

export type CountryInfo = {
  code: string;
  name: string;
  dial: string;
  flag: string;
  minDigits: number;
  maxDigits: number;
  placeholder: string;
  timezones: string[];
};

export const COUNTRIES: readonly CountryInfo[] = [
  // ─── Zone UEMOA (XOF / Franc CFA) ───────────────────────────
  {
    code: "BF",
    name: "Burkina Faso",
    dial: "+226",
    flag: "🇧🇫",
    minDigits: 8,
    maxDigits: 8,
    placeholder: "70 00 00 00",
    timezones: ["Africa/Ouagadougou"],
  },
  {
    code: "CI",
    name: "Côte d'Ivoire",
    dial: "+225",
    flag: "🇨🇮",
    minDigits: 10,
    maxDigits: 10,
    placeholder: "07 12 34 56 78",
    timezones: ["Africa/Abidjan"],
  },
  {
    code: "SN",
    name: "Sénégal",
    dial: "+221",
    flag: "🇸🇳",
    minDigits: 9,
    maxDigits: 9,
    placeholder: "77 123 45 67",
    timezones: ["Africa/Dakar"],
  },
  {
    code: "BJ",
    name: "Bénin",
    dial: "+229",
    flag: "🇧🇯",
    minDigits: 8,
    maxDigits: 10,
    placeholder: "90 12 34 56",
    timezones: ["Africa/Porto-Novo", "Africa/Cotonou"],
  },
  {
    code: "ML",
    name: "Mali",
    dial: "+223",
    flag: "🇲🇱",
    minDigits: 8,
    maxDigits: 8,
    placeholder: "70 12 34 56",
    timezones: ["Africa/Bamako"],
  },
  {
    code: "NE",
    name: "Niger",
    dial: "+227",
    flag: "🇳🇪",
    minDigits: 8,
    maxDigits: 8,
    placeholder: "90 12 34 56",
    timezones: ["Africa/Niamey"],
  },
  {
    code: "TG",
    name: "Togo",
    dial: "+228",
    flag: "🇹🇬",
    minDigits: 8,
    maxDigits: 8,
    placeholder: "90 12 34 56",
    timezones: ["Africa/Lome"],
  },
  {
    code: "GW",
    name: "Guinée-Bissau",
    dial: "+245",
    flag: "🇬🇼",
    minDigits: 7,
    maxDigits: 9,
    placeholder: "95 123 45 67",
    timezones: ["Africa/Bissau"],
  },

  // ─── Zone CEMAC & Autres pays (CinetPay) ────────────────────────
  {
    code: "CM",
    name: "Cameroun",
    dial: "+237",
    flag: "🇨🇲",
    minDigits: 9,
    maxDigits: 9,
    placeholder: "6 70 12 34 56",
    timezones: ["Africa/Douala"],
  },
  {
    code: "CD",
    name: "RDC",
    dial: "+243",
    flag: "🇨🇩",
    minDigits: 9,
    maxDigits: 9,
    placeholder: "81 234 56 78",
    timezones: ["Africa/Kinshasa", "Africa/Lubumbashi"],
  },
  {
    code: "CG",
    name: "Congo",
    dial: "+242",
    flag: "🇨🇬",
    minDigits: 9,
    maxDigits: 9,
    placeholder: "06 123 45 67",
    timezones: ["Africa/Brazzaville"],
  },
  {
    code: "GA",
    name: "Gabon",
    dial: "+241",
    flag: "🇬🇦",
    minDigits: 7,
    maxDigits: 8,
    placeholder: "74 12 34 56",
    timezones: ["Africa/Libreville"],
  },
  {
    code: "GN",
    name: "Guinée",
    dial: "+224",
    flag: "🇬🇳",
    minDigits: 9,
    maxDigits: 9,
    placeholder: "620 12 34 56",
    timezones: ["Africa/Conakry"],
  },
  {
    code: "GH",
    name: "Ghana",
    dial: "+233",
    flag: "🇬🇭",
    minDigits: 9,
    maxDigits: 10,
    placeholder: "20 123 4567",
    timezones: ["Africa/Accra"],
  },
  {
    code: "NG",
    name: "Nigeria",
    dial: "+234",
    flag: "🇳🇬",
    minDigits: 10,
    maxDigits: 11,
    placeholder: "802 123 4567",
    timezones: ["Africa/Lagos"],
  },
  {
    code: "KE",
    name: "Kenya",
    dial: "+254",
    flag: "🇰🇪",
    minDigits: 9,
    maxDigits: 10,
    placeholder: "712 345678",
    timezones: ["Africa/Nairobi"],
  },
  {
    code: "RW",
    name: "Rwanda",
    dial: "+250",
    flag: "🇷🇼",
    minDigits: 9,
    maxDigits: 9,
    placeholder: "78 123 4567",
    timezones: ["Africa/Kigali"],
  },
  {
    code: "TZ",
    name: "Tanzanie",
    dial: "+255",
    flag: "🇹🇿",
    minDigits: 9,
    maxDigits: 9,
    placeholder: "71 234 5678",
    timezones: ["Africa/Dar_es_Salaam"],
  },
  {
    code: "UG",
    name: "Ouganda",
    dial: "+256",
    flag: "🇺🇬",
    minDigits: 9,
    maxDigits: 9,
    placeholder: "70 123 4567",
    timezones: ["Africa/Kampala"],
  },
  {
    code: "ZM",
    name: "Zambie",
    dial: "+260",
    flag: "🇿🇲",
    minDigits: 9,
    maxDigits: 9,
    placeholder: "97 123 4567",
    timezones: ["Africa/Lusaka"],
  },
  // ─── Option universelle internationale ────────────────────────
  {
    code: "XX",
    name: "Autre pays",
    dial: "+",
    flag: "🌍",
    minDigits: 6,
    maxDigits: 15,
    placeholder: "Ex. +33 6 12 34 56 78",
    timezones: [],
  },
] as const;

export type CountryCode = (typeof COUNTRIES)[number]["code"];
export const COUNTRY_CODES = COUNTRIES.map((c) => c.code) as [CountryCode, ...CountryCode[]];

/**
 * Pays proposés pour PAYER (achat et recharge) : uniquement ceux dont la devise vaut 1 pour 1 avec le FCFA (XOF / XAF).
 * Doit rester identique à `CinetPayService::PAYABLE_COUNTRIES` côté serveur. Le profil garde la liste complète.
 * La détection automatique du pays se fait côté serveur (en-tête de l'hébergeur) : les fuseaux horaires des navigateurs
 * ne distinguent plus le Burkina, le Mali, le Sénégal ou le Togo de la Côte d'Ivoire.
 */
export const PAYMENT_COUNTRY_CODES = ["BF", "CI", "SN", "BJ", "ML", "NE", "TG", "GW", "CM", "CG", "GA"] as const;
export const PAYMENT_COUNTRIES: readonly CountryInfo[] = COUNTRIES.filter((c) => (PAYMENT_COUNTRY_CODES as readonly string[]).includes(c.code));

/** Trouve les informations d'un pays par son code (insensible à la casse). */
export function getCountry(code?: string | null): CountryInfo {
  if (!code) return COUNTRIES[0];
  const upper = code.trim().toUpperCase();
  return (
    COUNTRIES.find((c) => c.code === upper) ?? {
      code: "XX",
      name: "Autre pays",
      dial: "+",
      flag: "🌍",
      minDigits: 6,
      maxDigits: 15,
      placeholder: "Numéro de téléphone",
      timezones: [],
    }
  );
}

/**
 * Valide et extrait les chiffres locaux d'un numéro pour un pays donné.
 * Retourne les détails de validation (longueur exacte attendue).
 */
export function validatePhoneForCountry(
  rawPhone: string,
  countryCode: string,
): { valid: boolean; digits: string; message?: string } {
  const country = getCountry(countryCode);
  let digits = rawPhone.replace(/^00/, "").replace(/[^0-9]/g, "");
  const dialDigits = country.dial.replace(/[^0-9]/g, "");

  // Si l'utilisateur a collé l'indicatif au début du champ (ex: 22670001122)
  if (digits.startsWith(dialDigits) && digits.length > dialDigits.length) {
    digits = digits.slice(dialDigits.length);
  }

  if (country.minDigits === country.maxDigits) {
    if (digits.length !== country.minDigits) {
      return {
        valid: false,
        digits,
        message: `Le numéro pour ${country.name} doit comporter exactement ${country.minDigits} chiffres (${digits.length} saisi${digits.length > 1 ? "s" : ""}).`,
      };
    }
  } else {
    if (digits.length < country.minDigits || digits.length > country.maxDigits) {
      return {
        valid: false,
        digits,
        message: `Le numéro pour ${country.name} doit comporter entre ${country.minDigits} et ${country.maxDigits} chiffres.`,
      };
    }
  }

  return { valid: true, digits };
}
