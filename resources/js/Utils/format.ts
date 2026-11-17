const nf = new Intl.NumberFormat("fr-FR", { maximumFractionDigits: 0 });
const usd = new Intl.NumberFormat("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** Montant en francs CFA (le site est en FCFA uniquement). */
export function formatXof(amount: number): string {
  return `${nf.format(amount)} FCFA`;
}

export function formatCurrency(amount: number, currency: 'XOF' | 'USD' = 'XOF', rate = 669): string {
  if (currency === 'USD') {
    return `${usd.format(amount / rate)} $`;
  }
  return formatXof(amount);
}

export function formatUsdCents(cents: number): string {
  return `${usd.format(cents / 100)} $`;
}

const dateTime = new Intl.DateTimeFormat("fr-FR", { dateStyle: "medium", timeStyle: "short", timeZone: "UTC" });
const dateOnly = new Intl.DateTimeFormat("fr-FR", { dateStyle: "medium", timeZone: "UTC" });

export function formatDateTime(date: Date | null | undefined): string {
  return date ? `${dateTime.format(date)} UTC` : "—";
}

export function formatDate(date: Date | null | undefined): string {
  return date ? dateOnly.format(date) : "—";
}

export function formatDuration(days: number): string {
  if (!days) return "Selon l'offre";
  if (days % 365 === 0) return days === 365 ? "1 an" : `${days / 365} ans`;
  if (days % 30 === 0) return days === 30 ? "1 mois" : `${days / 30} mois`;
  return `${days} jours`;
}

/** Traduit la garantie fournisseur (« 30 days ») en français. */
export function formatWarranty(warranty: string): string {
  const value = warranty.trim();
  if (!value) return "Aucune garantie annoncée";
  if (/^no\s*warranty$/i.test(value) || /^nw$/i.test(value)) return "Aucune garantie";
  if (/^full\s*warranty$/i.test(value) || /^fw$/i.test(value)) return "Garantie sur toute la durée";
  if (/^\d+$/.test(value)) return `${value} jour${Number(value) > 1 ? "s" : ""}`;
  const compact = value.match(/^W(\d+)([DHM])$/i);
  if (compact) return `${compact[1]} ${compact[2].toUpperCase() === "D" ? "jour" + (Number(compact[1]) > 1 ? "s" : "") : compact[2].toUpperCase() === "H" ? "heure" + (Number(compact[1]) > 1 ? "s" : "") : "mois"}`;
  const match = value.match(/^(\d+)\s*(day|days|month|months|year|years|hour|hours)(?:\s*warranty)?$/i);
  if (!match) return value;
  const n = Number(match[1]);
  const unit = match[2].toLowerCase();
  if (unit.startsWith("day")) return `${n} jour${n > 1 ? "s" : ""}`;
  if (unit.startsWith("month")) return `${n} mois`;
  if (unit.startsWith("hour")) return `${n} heure${n > 1 ? "s" : ""}`;
  return `${n} an${n > 1 ? "s" : ""}`;
}
