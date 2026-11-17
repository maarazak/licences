// Faux fournisseur + faux pawaPay pour tester le parcours d'achat en local, sans
// vraies clés ni vrai argent. NE JAMAIS utiliser en production.
//
//   node scripts/dev-mock.mjs            (écoute sur 127.0.0.1:4010)
//   SUPPLIER_API_URL=http://127.0.0.1:4010/supplier/api/v1 SUPPLIER_API_KEY=mock \
//   PAWAPAY_API_URL=http://127.0.0.1:4010/pawapay/v2 PAWAPAY_API_TOKEN=mock npm run dev
//
// Pilotage : POST /_control  {"balance": 50, "nextOrder": "completed", "payment": "completed"}
//   nextOrder : completed | activation_awaiting | delivery_failed | insufficient_stock | timeout
//   payment   : pending | completed | failed | expired | wrong_amount
import { createServer } from "node:http";
import { randomUUID } from "node:crypto";

const state = { balance: 50, nextOrder: "completed", payment: "pending" };
const products = [
  { id: "demo-1", slug: "demo-outil-ia", name: "[DEMO] Outil IA Plus", category: "IA et assistants", image_url: null, delivery_kind: "account", price: 3, list_price: 4, stock: 54, in_stock: true, preorder: false, expire_days: 30, warranty: "30 days", requires_email: false, purchasable: true },
  { id: "demo-2", slug: "demo-creation-pro", name: "[DEMO] Création Pro", category: "Création", image_url: null, delivery_kind: "activation_link", price: 1.5, list_price: 2, stock: null, in_stock: true, preorder: false, expire_days: 365, warranty: "", requires_email: true, purchasable: true },
  { id: "demo-3", slug: "demo-bureautique", name: "[DEMO] Bureautique", category: "Bureautique", image_url: null, delivery_kind: "code", price: 2, list_price: 2.5, stock: 0, in_stock: false, preorder: false, expire_days: 365, warranty: "7 days", requires_email: false, purchasable: true },
];
const orders = new Map(); // id → commande fournisseur
const idem = new Map(); // clé d'idempotence → id de commande
const checkouts = new Map(); // id → session Orqex

const send = (res, status, body) => {
  res.writeHead(status, { "content-type": "application/json" });
  res.end(JSON.stringify(body));
};
const fail = (res, status, code, message) => send(res, status, { error: { code, message } });
const readJson = async (req) => {
  let raw = "";
  for await (const chunk of req) raw += chunk;
  return raw ? JSON.parse(raw) : {};
};

createServer(async (req, res) => {
  const url = new URL(req.url, "http://mock");
  const path = url.pathname;
  try {
    if (path === "/_control" && req.method === "POST") {
      Object.assign(state, await readJson(req));
      return send(res, 200, state);
    }
    if (path === "/_state") return send(res, 200, { state, orders: [...orders.values()], checkouts: [...checkouts.values()] });

    // ── Fournisseur ──
    if (path.startsWith("/supplier/api/v1")) {
      if (!/^Bearer .+/.test(req.headers.authorization ?? "")) return fail(res, 401, "unauthorized", "Clé absente");
      const p = path.slice("/supplier/api/v1".length);
      if (p === "/products") return send(res, 200, { data: products, pagination: { page: 1, per_page: 100, has_more: false } });
      if (p.startsWith("/products/")) {
        const product = products.find((x) => x.id === decodeURIComponent(p.slice(10)) || x.slug === decodeURIComponent(p.slice(10)));
        return product ? send(res, 200, { data: product }) : fail(res, 404, "product_not_found", "Produit inconnu");
      }
      if (p === "/balance") return send(res, 200, { telegram_id: 1, username: "mock", balance: state.balance, currency: "USD", orders_count: orders.size });
      if (p === "/orders" && req.method === "POST") {
        const key = req.headers["idempotency-key"];
        if (key && idem.has(key)) return send(res, 200, { ...orders.get(idem.get(key)), idempotent_replay: true });
        const body = await readJson(req);
        const product = products.find((x) => x.id === body.product || x.slug === body.product);
        if (!product) return fail(res, 404, "product_not_found", "Produit inconnu");
        const quantity = body.quantity ?? 1;
        const total = product.price * quantity;
        if (state.nextOrder === "timeout") return; // ne répond jamais : simule une coupure
        if (state.nextOrder === "insufficient_stock") return fail(res, 409, "insufficient_stock", "Stock insuffisant");
        if (state.balance < total) return fail(res, 402, "insufficient_balance", "Solde insuffisant");
        if (state.nextOrder === "delivery_failed") return fail(res, 502, "delivery_failed", "Livraison impossible, remboursé");
        state.balance = Math.round((state.balance - total) * 100) / 100;
        const awaiting = state.nextOrder === "activation_awaiting";
        const order = {
          id: randomUUID().replace(/-/g, "").slice(0, 24),
          reference: `SUP-${orders.size + 1}`,
          status: awaiting ? "activation_awaiting" : "completed",
          product: product.name,
          quantity,
          unit_price: product.price,
          total,
          currency: "USD",
          items: awaiting ? [] : Array.from({ length: quantity }, (_, i) => `demo${i + 1}@exemple.test | MotDePasse-${i + 1} | https://exemple.test/activer/${i + 1}`),
          balance: state.balance,
        };
        orders.set(order.id, order);
        if (key) idem.set(key, order.id);
        return send(res, 200, order);
      }
      if (p.startsWith("/orders/")) {
        const order = orders.get(p.slice(8));
        return order ? send(res, 200, order) : fail(res, 404, "order_not_found", "Commande inconnue");
      }
    }

    // ── pawaPay v2 ──
    if (path.startsWith("/pawapay/v2") || path.startsWith("/v2")) {
      if (!/^Bearer .+/.test(req.headers.authorization ?? "")) return send(res, 401, { message: "Unauthenticated." });
      const p = path.startsWith("/pawapay/v2") ? path.slice("/pawapay/v2".length) : path.slice("/v2".length);

      if (p === "/paymentpage" && req.method === "POST") {
        const body = await readJson(req);
        const depositId = body.depositId || randomUUID();
        const amount = body.amountDetails?.amount ?? "1000";
        const redirectUrl = `https://paywith.sandbox.pawapay.io/?token=${randomUUID()}`;
        checkouts.set(depositId, {
          depositId,
          amount: Number(amount),
          currency: body.amountDetails?.currency ?? "XOF",
          redirectUrl,
          reference: body.customerMessage || "CMD",
        });
        return send(res, 200, { depositId, status: "ACCEPTED", redirectUrl });
      }

      if (p.startsWith("/deposits/")) {
        const depositId = decodeURIComponent(p.slice("/deposits/".length));
        const c = checkouts.get(depositId);
        if (!c) return send(res, 404, { status: "NOT_FOUND", message: "Deposit not found." });

        let rawStatus = "ACCEPTED";
        if (state.payment === "completed") rawStatus = "COMPLETED";
        else if (state.payment === "failed") rawStatus = "FAILED";
        else if (state.payment === "expired") rawStatus = "FAILED";

        const value = state.payment === "wrong_amount" ? c.amount - 100 : c.amount;

        return send(res, 200, {
          status: "FOUND",
          data: {
            depositId: c.depositId,
            status: rawStatus,
            amount: String(value),
            currency: c.currency,
            country: "CIV",
            payer: {
              type: "MMO",
              accountDetails: {
                phoneNumber: "2250700000000",
                provider: "MTN_MOMO_CIV",
              },
            },
            customerMessage: c.reference,
            providerTransactionId: `pt_${randomUUID().slice(0, 8)}`,
            metadata: { orderId: c.depositId },
            ...(rawStatus === "FAILED" ? { failureReason: { failureCode: "PAYMENT_REJECTED", failureMessage: "Paiement rejeté par le client." } } : {}),
          },
        });
      }
    }
    send(res, 404, { message: "Route inconnue du mock" });
  } catch (error) {
    send(res, 500, { message: String(error) });
  }
}).listen(4010, "127.0.0.1", () => console.log("Mock fournisseur + pawaPay sur http://127.0.0.1:4010"));
