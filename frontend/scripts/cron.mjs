// Déclenche la tâche planifiée (à lancer chaque minute par cron en production,
// ou en boucle en développement : `npm run cron -- --watch`).
import "dotenv/config";

const url = new URL("/api/cron/tick", process.env.APP_URL ?? "http://localhost:3000");

async function tick() {
  try {
    const res = await fetch(url, { method: "POST", headers: { authorization: `Bearer ${process.env.CRON_SECRET}` } });
    console.log(new Date().toISOString(), res.status, await res.text());
  } catch (error) {
    console.error(new Date().toISOString(), "échec :", error.message);
  }
}

await tick();
if (process.argv.includes("--watch")) setInterval(tick, 60_000);
