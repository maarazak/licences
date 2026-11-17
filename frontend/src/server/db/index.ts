import "server-only";
import { drizzle, type MySql2Database } from "drizzle-orm/mysql2";
import mysql from "mysql2/promise";
import { env } from "@/server/env";
import * as schema from "./schema";

type Db = MySql2Database<typeof schema>;

const globalForDb = globalThis as unknown as { __pool?: mysql.Pool; __db?: Db };

function create(): Db {
  const pool =
    globalForDb.__pool ??
    mysql.createPool({
      uri: env().DATABASE_URL,
      connectionLimit: 10,
      timezone: "Z",
      charset: "utf8mb4",
      dateStrings: false,
      supportBigNumbers: true,
    });
  globalForDb.__pool = pool;
  return drizzle(pool, { schema, mode: "default" });
}

/** Connexion unique, réutilisée entre les rechargements à chaud en développement. */
export function db(): Db {
  if (!globalForDb.__db) globalForDb.__db = create();
  return globalForDb.__db;
}

export type Tx = Parameters<Parameters<Db["transaction"]>[0]>[0];

/** Nombre de lignes modifiées par un UPDATE / INSERT / DELETE (mysql2). */
export function affected(result: unknown): number {
  const header = Array.isArray(result) ? result[0] : result;
  return Number((header as { affectedRows?: number } | undefined)?.affectedRows ?? 0);
}

export { schema };
