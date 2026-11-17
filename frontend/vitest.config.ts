import { fileURLToPath } from "node:url";
import { defineConfig } from "vitest/config";

const r = (p: string) => fileURLToPath(new URL(p, import.meta.url));

export default defineConfig({
  resolve: {
    alias: [
      { find: /^server-only$/, replacement: r("./tests/integration/empty.ts") },
      { find: /^@\//, replacement: r("./src/") + "/" },
    ],
  },
  test: {
    include: ["tests/**/*.test.ts"],
    // Les tests d'intégration partagent la base `licences_test` : un fichier à la fois.
    fileParallelism: false,
    testTimeout: 30_000,
    hookTimeout: 30_000,
    env: {
      NODE_ENV: "test",
      APP_URL: "https://boutique.test",
      DATABASE_URL: "mysql://root@127.0.0.1:3306/licences_test",
      AUTH_SECRET: "test-auth-secret-0123456789abcdef0123456789",
      ENCRYPTION_KEY: Buffer.alloc(32, 7).toString("base64"),
      CRON_SECRET: "test-cron-secret-0123456789abcdef",
      ADMIN_EMAILS: "admin@boutique.test",
      SUPPLIER_API_URL: "http://fake.test/supplier/api/v1",
      SUPPLIER_API_KEY: "fake",
      PAWAPAY_API_URL: "http://fake.test/pawapay/v2",
      PAWAPAY_API_TOKEN: "fake_token",
      SMTP_HOST: "",
    },
  },
});
