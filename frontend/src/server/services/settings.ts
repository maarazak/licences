import "server-only";
import { cache } from "react";
import { db, schema } from "@/server/db";

/** Réglages de la boutique, modifiables dans le backoffice. */
export type ShopSettings = {
  /** Taux du dollar : FCFA pour 1 USD. Un seul taux, réglé dans le backoffice, pour le coût d'achat, les prix, le blocage des ventes à perte et l'affichage en dollars sur le site. */
  usdRate: number;
  /** Marge appliquée par défaut sur le coût, en pourcentage. */
  marginPercent: number;
  /** Gain minimal par vente, en FCFA : le prix est au moins « coût + cette marge ». */
  minMarginXof: number;
  /** Les prix calculés sont arrondis au multiple supérieur de ce pas (FCFA). */
  roundingStep: number;
  /** Publier automatiquement les nouveaux produits du fournisseur. */
  autoPublish: boolean;
  /** Bandeau d'information affiché en haut du site (vide = masqué). */
  notice: string;
  /** Date ISO de la dernière synchronisation du catalogue. */
  lastCatalogSyncAt: string;
  /** Dernière erreur de synchronisation (vide = dernière synchronisation réussie). */
  catalogSyncError: string;
  /** Dernier solde fournisseur lu (cents USD), recopié par la tâche planifiée : le backoffice n'interroge pas le fournisseur à l'affichage. */
  supplierBalanceCents: number;
  supplierBalanceAt: string;
  supplierBalanceError: string;
};

export const DEFAULT_SETTINGS: ShopSettings = {
  usdRate: 669,
  marginPercent: 100,
  minMarginXof: 1000,
  roundingStep: 250,
  autoPublish: false,
  notice: "",
  lastCatalogSyncAt: "",
  catalogSyncError: "",
  supplierBalanceCents: 0,
  supplierBalanceAt: "",
  supplierBalanceError: "",
};

function parse(rows: Array<{ key: string; value: string }>): ShopSettings {
  const map = new Map(rows.map((r) => [r.key, r.value]));
  const int = (key: keyof ShopSettings, min: number, max: number) => {
    const n = Number.parseInt(map.get(key) ?? "", 10);
    return Number.isFinite(n) && n >= min && n <= max ? n : (DEFAULT_SETTINGS[key] as number);
  };
  return {
    usdRate: int("usdRate", 1, 100_000),
    marginPercent: int("marginPercent", 0, 1000),
    minMarginXof: int("minMarginXof", 0, 1_000_000),
    roundingStep: int("roundingStep", 1, 10_000),
    autoPublish: map.get("autoPublish") === "true",
    notice: map.get("notice") ?? "",
    lastCatalogSyncAt: map.get("lastCatalogSyncAt") ?? "",
    catalogSyncError: map.get("catalogSyncError") ?? "",
    supplierBalanceCents: Number.parseInt(map.get("supplierBalanceCents") ?? "", 10) || 0,
    supplierBalanceAt: map.get("supplierBalanceAt") ?? "",
    supplierBalanceError: map.get("supplierBalanceError") ?? "",
  };
}

export async function loadSettings(): Promise<ShopSettings> {
  return parse(await db().select({ key: schema.settings.key, value: schema.settings.value }).from(schema.settings));
}

/** Version mémorisée pour la durée d'une requête. */
export const getSettings = cache(loadSettings);

export async function saveSettings(values: Partial<ShopSettings>): Promise<void> {
  const now = new Date();
  for (const [key, value] of Object.entries(values)) {
    if (value === undefined) continue;
    const text = String(value);
    await db()
      .insert(schema.settings)
      .values({ key, value: text, updatedAt: now })
      .onDuplicateKeyUpdate({ set: { value: text, updatedAt: now } });
  }
}
