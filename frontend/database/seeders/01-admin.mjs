// Crée (ou met à jour) un compte administrateur avec e-mail et mot de passe. Relançable sans risque.
// Les administrateurs n'existent que par ce seeder : aucune adresse n'est écrite dans le .env.
// Sans terminal : ADMIN_EMAIL=… ADMIN_PASSWORD=… npm run db:seed -- 01-admin (valeurs à passer sur la ligne de commande, jamais dans un fichier).
import { randomBytes, randomUUID, scrypt } from "node:crypto";

const N = 16384, R = 8, P = 1, KEYLEN = 64;

const seeder = {
  interactive: !process.env.ADMIN_PASSWORD,
  async run({ db, ask }) {
    const given = process.env.ADMIN_PASSWORD;
    const email = (given ? (process.env.ADMIN_EMAIL ?? "") : await ask("E-mail de l'administrateur : ")).trim().toLowerCase();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || email.length > 191) throw new Error("Adresse e-mail invalide.");
    const pw1 = given ?? (await ask("Mot de passe (8 caractères minimum) : ", true));
    if (pw1.length < 8) throw new Error("Trop court : 8 caractères minimum.");
    if (!given && pw1 !== (await ask("Confirmez le mot de passe : ", true))) throw new Error("Les deux saisies diffèrent.");

    const salt = randomBytes(16);
    const key = await new Promise((res, rej) => scrypt(pw1, salt, KEYLEN, { N, r: R, p: P, maxmem: 64 * 1024 * 1024 }, (e, k) => (e ? rej(e) : res(k))));
    // Même format que src/server/auth/password.ts
    const hash = ["scrypt", N, R, P, salt.toString("base64url"), key.toString("base64url")].join("$");

    const [rows] = await db.query("select id from users where email = ?", [email]);
    let userId;
    if (rows.length) {
      userId = rows[0].id;
      await db.query("update users set password_hash = ?, role = 'admin', is_blocked = 0 where id = ?", [hash, userId]);
    } else {
      userId = randomUUID();
      await db.query("insert into users (id, email, role, password_hash, balance_xof, lang, mail_reminders, is_blocked, created_at) values (?, ?, 'admin', ?, 0, 'fr', 1, 0, ?)", [userId, email, hash, new Date()]);
    }
    // Un changement de mot de passe ferme les sessions existantes de ce compte.
    await db.query("delete from sessions where user_id = ?", [userId]);
    console.log(`  Administrateur enregistré : ${email} (connexion : /admin/connexion)`);
  },
};

export default seeder;
