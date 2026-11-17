# CLAUDE.md — Licences : boutique de licences numériques (FCFA)

@AGENTS.md

## Démarrage obligatoire de chaque session & autonomie

1. Lire `TRAVAIL_EN_COURS.md` à la racine.
2. **Autonomie totale** : travailler sans demander de confirmation pour modifier des fichiers, lancer des commandes ou compiler, tant que c'est dans le cadre de la demande.
3. Toujours répondre en français, de façon concise.
4. Ne jamais commit ni push sans accord explicite.
5. Pour les modèles et la délégation à un modèle plus puissant : voir `~/.claude/CLAUDE.md` (autorisation permanente de l'utilisateur).

---

## Le projet

Boutique de revente d'abonnements et licences numériques, **prix en FCFA**, inspirée de getpass.shop. Trois espaces dans une seule application Next.js :

| Espace | Dossier | Accès |
|---|---|---|
| Vitrine + espace client | `src/app/(site)/` | public / client connecté |
| Backoffice | `src/app/admin/` | administrateurs (rôle `admin` en base, e-mail + mot de passe puis **code de vérification reçu par e-mail** sur `/admin/connexion`) |
| API (webhook, tâche planifiée) | `src/app/api/` | secrets / signature |

- **Stack** : Next.js 16 (App Router, React 19, Server Actions) · TypeScript · Tailwind 4 · MySQL (DBngin en local) · Drizzle ORM · zod · nodemailer · vitest.
- **Fournisseur** : API revendeur Flowseller (`https://flowseller-api.pe-supplykh.com/docs`), facturée en USD sur un solde prépayé rechargé dans le bot Telegram (pas d'API de recharge).
- **Paiement** : pawaPay v2 (agrégateur Mobile Money panafricain) en « hosted payment page » (`POST /v2/paymentpage`) ; `https://docs.pawapay.io/v2/`. Intègre directement tous les opérateurs télécoms (MTN, Orange, Moov, Wave, Airtel, M-Pesa…).
- **E-mail** : SMTP Brevo (`smtp-relay.brevo.com:587`). Sans `SMTP_HOST`, les e-mails s'affichent dans la console du serveur (dev uniquement).
- **Base locale** : MySQL 9.7 de DBngin, `127.0.0.1:3306`, base `licences`, utilisateur `root` sans mot de passe (visible dans TablePlus).

> Next.js 16 diffère de ce que le modèle connaît (`proxy.ts` remplace `middleware.ts`, `params`/`searchParams`/`cookies()` sont asynchrones…). **Lire `node_modules/next/dist/docs/` avant d'utiliser une API Next.**

---

## Architecture

```
src/
  app/
    (site)/            vitrine : /, /boutique, /boutique/[slug], /commande/validation, /paiement/retour,
                       /connexion, /compte/** (tableau de bord, achats, accès, solde, alertes, assistance,
                       paramètres), pages légales
    admin/             backoffice : tableau de bord, commandes, produits, clients, assistance, paramètres, journal
    api/webhooks/pawapay notification de paiement (relit l'état chez pawaPay, ne croit jamais le contenu reçu)
    api/cron/tick      tâche planifiée (Authorization: Bearer CRON_SECRET)
  components/          composants UI (forms.tsx = formulaires/actions, ui.tsx, site-chrome.tsx…)
  lib/                 code partagé client/serveur SANS secret : enums, format, pricing, site, action-state
  server/              code serveur uniquement (`import "server-only"`)
    env.ts             variables d'environnement validées (zod)
    db/                schema Drizzle + connexion
    auth/              otp.ts (codes), session.ts (sessions, garde admin)
    security/          crypto.ts, rate-limit.ts, request.ts
    services/          métier : catalog, orders, pawapay, supplier, wallet, alerts, reminders, tickets, settings, audit, admin
    actions/           Server Actions : auth.ts, shop.ts, admin.ts
    mail/              mailer.ts, templates.ts
    validators.ts      schémas zod de toutes les entrées
  proxy.ts             CSP avec nonce
database/migrations/   migrations Drizzle (SQL versionné)
scripts/               generate-secrets, cron, dev-mock (faux fournisseur + faux Orqex)
tests/                 vitest
```

Règles d'architecture :
- **Logique métier dans `src/server/services/`**, jamais dans les pages ni les actions. Les actions valident (zod), contrôlent les droits, appellent un service.
- **Validation** : tout ce qui vient du navigateur passe par `src/server/validators.ts`.
- **Textes en français**, formats via `src/lib/format.ts`.

---

## Règles de sécurité (non négociables)

- **Preuve de paiement** : seul `syncPayment()` (relecture de la session auprès de pawaPay avec le jeton secret, contrôle de l'identifiant, du montant et de la devise XOF) fait passer une commande à « payée ». Ni le retour client, ni le webhook, ni un paramètre d'URL ne valent preuve.
- **Transitions de statut atomiques** : `transition()` = `UPDATE … WHERE status IN (…)`. Ne jamais lire puis écrire sans cette garde.
- **Un seul achat fournisseur par commande** : verrou `fulfilLockedAt` + clé d'idempotence stable `supplierIdemKey`, renouvelée seulement après un échec *définitif* (`adminRetryFulfilment`).
- **Solde client** : toute écriture passe par `services/wallet.ts` (débit conditionnel `balance >= montant` + ligne dans `wallet_entries`, dans la même transaction). Ne jamais modifier `users.balance_xof` ailleurs.
- **Accès livrés** : chiffrés AES-256-GCM (`ENCRYPTION_KEY`), jamais envoyés par e-mail, déchiffrés uniquement pour le propriétaire (`getUserOrder` filtre par `userId`) ou l'admin (consultation journalisée).
- **Paiement tardif** : `syncPayment` relit aussi les commandes `expired` / `payment_failed` ; un paiement qui aboutit après la clôture passe la commande à « payée » et la livre. Ne jamais clore une commande sans relire pawaPay avant.
- **Issue incertaine** (`too_many_attempts`, `empty_delivery`, `amount_mismatch`, liste `HOLD_CODES`) : le client ne choisit pas seul avoir/remboursement ; la relance admin garde la même clé d'idempotence (≤ 23 h). Ne jamais générer une nouvelle clé après un appel fournisseur dont l'issue est inconnue.
- **Remboursement demandé** (`refundRequestedAt`) : le client ne peut plus prendre l'avoir (transition conditionnelle).
- **Prix** : le total affiché est renvoyé au serveur (`expectedTotalXof`) et refusé s'il a changé ; avant l'achat fournisseur, `fulfil` bloque une vente devenue à perte.
- **Droits** : chaque Server Action revérifie (`actionUser` / `actionAdmin`). La protection des pages ne suffit pas.
- **Aucun lien du site client vers le backoffice** (pas de bouton, pas de redirection après connexion) ; `/admin` renvoie **404** à quiconque n'est pas administrateur et n'apparaît pas dans `robots.txt`. Les administrateurs saisissent l'adresse directement.
- **Admin** = rôle `admin` en base uniquement (`isAdmin()`), créé par le seeder `npm run db:seed` (e-mail + mot de passe haché scrypt, jamais dans le `.env`). Les administrateurs ne se connectent **pas** par le code e-mail des clients : formulaire distinct `/admin/connexion` en **deux facteurs** — `adminLoginAction` (mot de passe correct → code à 6 chiffres envoyé à l'adresse de l'admin + cookie de défi `admin2fa`), puis `adminVerifyCodeAction` (code valable 10 min, usage unique, 5 essais ; la session n'est ouverte qu'à ce moment). Sans le cookie de défi (donc sans mot de passe), le code ne peut pas être essayé. Si l'e-mail ne part pas, la connexion admin est impossible (pas de contournement) : vérifier le SMTP. **Chaque page `src/app/admin/**` appelle `requireAdmin()` elle-même** : le layout seul ne suffit pas (il ne se ré-exécute pas à chaque navigation).
- **Adresse IP** : `clientIp()` ne croit `x-real-ip` (ou autre) que si `TRUSTED_IP_HEADER` le déclare (reverse proxy qui l'écrase) ; sinon dernière entrée de `X-Forwarded-For` (`TRUSTED_PROXY_HOPS` pour remonter), jamais la première (falsifiable).
- **Secrets** : jamais dans le code, jamais dans le client. `.env` est ignoré par git. Ne pas afficher de valeur de secret dans les réponses. **Contrôle automatique avant chaque commit** : `.githooks/pre-commit` → `scripts/check-secrets.mjs` (formats de clés connus, secrets affectés en clair, mots de passe dans les adresses de base, fichiers `.env`/`.pem`/`.key`, et toute valeur réelle du `.env` local). Activé par `npm install` (`prepare` → `core.hooksPath`). `npm run check:secrets` analyse tout le dépôt. Un commit bloqué ne se contourne pas avec `--no-verify` : retirer le secret ; s'il a déjà été poussé, le renouveler chez le fournisseur.
- **Redirections** : `safeNextPath()` pour tout paramètre `next` ; `isSafeCheckoutUrl()` (HTTPS, `*.pawapay.io`) avant d'envoyer un client chez le prestataire.
- Pas de `dangerouslySetInnerHTML`. Pas de SQL concaténé (paramètres Drizzle/`sql` tagué uniquement).

---

## Langues (français / anglais)

- Le texte français sert de clé : `t("Voir les offres")` (serveur : `const { t } = await getI18n()` ; navigateur : `useI18n()`). Variables : `t("{n} offres", { n })`. Langue = cookie `lang` (choisie par le visiteur), **français par défaut, jamais déduite du navigateur** ; mémorisée aussi dans `users.lang` (les e-mails partent dans la langue du client).
- **Ajouter un texte** : l'écrire avec `t("…")`, ajouter sa traduction dans `scripts/build-i18n.py`, puis `python3 scripts/extract-i18n.py && python3 scripts/build-i18n.py` (régénère `src/lib/i18n-en.ts` — ne pas l'éditer à la main). `tests/i18n.test.ts` échoue si une clé manque.
- Les messages d'erreur/succès renvoyés par les actions serveur restent en français et sont traduits côté navigateur par `FormMessage` ; les messages à valeurs passent par `EN_PATTERNS`.
- Le **backoffice reste en français**. Les textes produit ont une version anglaise facultative (`description_en`, `instructions_en`).

## Devises

Le site s'affiche en **FCFA ou en dollars** (sélecteur dans l'en-tête, cookie `devise`) ; **le paiement est toujours en FCFA** (le total FCFA est rappelé). **Un seul taux du dollar** : le réglage « Taux du dollar : 1 $ en FCFA » du backoffice (`settings.usdRate`, 669 par défaut). Il sert au coût d'achat, aux prix de vente, au blocage des ventes à perte et à l'affichage en dollars sur le site (« Taux du jour : 1 $ = 669 FCFA »). **Aucun taux de change externe** (le taux réel BCE a été retiré à la demande du propriétaire : ne pas le réintroduire). Code : `src/lib/money.ts`, `src/server/prefs.ts`.

## Catalogue : actualisation

**La boutique ne lit jamais le fournisseur pour afficher** : visiteurs, boutique, fiches et espace client lisent uniquement la table `products` de notre base. Le catalogue y est recopié par `syncCatalog()` (`services/catalog.ts`) : **tâche planifiée chaque minute** (`services/scheduler.ts`, `runTick`) et bouton « Synchroniser » du backoffice. La synchro est incrémentale : seuls les produits dont un champ fournisseur (prix, stock, état, textes…) a changé sont réécrits ; un produit absent de la liste est désactivé ; une liste vide ne désactive rien. Les réglages boutique (publication, prix fixe, descriptions) ne sont jamais touchés. **Le fournisseur annonce parfois, pendant quelques minutes, « stock 0 / non vendable » à tort** (observé : ~26 produits à stock non compté, qui reviennent seuls) : un produit vendable annoncé indisponible garde sa disponibilité tant que 5 lectures consécutives ne le confirment pas (`applyReading`, colonne `unavailable_readings`) ; prix, retour en stock et textes sont appliqués tout de suite ; un coût nul retire le produit immédiatement. À l'achat, le produit est de toute façon relu en direct. En cas d'échec du fournisseur, le catalogue enregistré reste en vente et l'erreur s'affiche sur le tableau de bord (alerte aussi si aucune synchro depuis 5 min). Au moment d'acheter, le produit est de toute façon relu en direct chez le fournisseur.

**Planificateur** : lancé avec le serveur (`instrumentation.ts` → `startScheduler()`, toutes les minutes, serveur permanent : `npm run dev`, VPS, Docker). `DISABLE_INTERNAL_SCHEDULER=1` le coupe si un cron externe appelle `POST /api/cron/tick` (hébergeur sans processus permanent). Un verrou en base empêche deux exécutions simultanées.

## Relances de paiement

`services/reminders.ts`, appelé par `/api/cron/tick`. Au plus **2 e-mails par commande** : (1) 20–50 min après la commande tant que la session est ouverte, (2) 90 min–24 h après si elle a expiré (lien pour recommencer). Jamais si le compte est suspendu ou a désactivé les rappels (Paramètres), si le client a déjà recommandé l'offre, si l'offre est indisponible, ni plus d'un rappel toutes les 6 h par client. Réservation atomique (`reminderCount`) : pas de doublon.

## Identité visuelle

- **Logo de la boutique** : monogramme « L » en ticket, vert `#0d5c4a` + pastille orange (`src/components/brand.tsx`, `public/brand/licences-mark.svg`, `src/app/icon.svg`). Nom : `NEXT_PUBLIC_APP_NAME` (défaut « Licences »).
- **Design** : thème **clair** (fond blanc, halos pastel vert/orange), cartes à logo, bandeau de marques défilant, carrousel d'offres, produits phares en premier. **Pas d'émojis ni de caractères décoratifs (→ ✓ …) : uniquement de vraies icônes `lucide-react`.** Jetons (couleurs, ombres) et composants (`.btn-*`, `.card`, `.chip`, `.glass`, `.bg-hero`, animations) dans `src/app/globals.css`. **Aucun style en ligne** (`style=""`) : la CSP de production les bloque ; utiliser des classes ou des attributs SVG. Ne jamais mettre une classe personnalisée dans `@apply` (Tailwind refuse et tout le CSS cesse de se compiler).
- **Logos des marques des produits** : `src/server/brands.ts` associe le nom d'un produit à une marque. Ordre : fichier `public/brands/<clé>.(svg|png|webp)` > pictogramme du paquet `simple-icons` > monogramme coloré. Pour ajouter ou remplacer un logo, déposer le fichier dans `public/brands/` (clés : canva, capcut, chatgpt, adobe, primevideo, office, linkedin, scribd, lovable, gamma, granola, arize, shahid, hma, pangram, muse, mobbin, magicpatterns…). Les images du fournisseur ne sont volontairement pas utilisées.

## Prix

`prix = ⌈ max( coût × (1 + marge %), coût + marge minimale FCFA ) / pas ⌉ × pas` avec `coût = coût_USD × taux` (`src/lib/pricing.ts`). Réglages par défaut : taux du dollar 669, **marge 100 %**, marge minimale 1 000 FCFA, arrondi 250 FCFA ; modifiables dans le backoffice > Paramètres. **Taux unique** `settings.usdRate` (aucun taux réel externe). Prix fixe (prioritaire) ou marge propre en % par produit. **Une vente dont le prix est inférieur au coût est bloquée** (`coversCost`).

---

## Base de données

- Toute évolution passe par une **nouvelle migration** : `npm run db:generate` puis `npm run db:migrate`. Ne jamais modifier une migration déjà appliquée en production.
- Montants FCFA en entiers ; montants USD en cents ; dates en UTC.
- `products` : colonnes issues du fournisseur (écrasées à chaque synchro) et réglages boutique (`is_published`, `display_name`, `shop_category`, `duration_label`, `description`, `instructions`, prix/marge) **jamais écrasés** par la synchro.

---

## Commandes fréquentes

```bash
npm run dev            # serveur de développement (http://localhost:3000)
npm run build          # build de production
npm run typecheck      # tsc --noEmit
npm run lint           # eslint
npm test               # vitest
npm run db:generate    # nouvelle migration depuis schema.ts
npm run db:migrate     # appliquer les migrations
npm run secrets        # générer AUTH_SECRET / ENCRYPTION_KEY / CRON_SECRET
npm run cron -- --watch  # déclencher la tâche planifiée chaque minute (dev)

# Tester le parcours d'achat sans vraies clés ni vrai argent :
node scripts/dev-mock.mjs
SUPPLIER_API_URL=http://127.0.0.1:4010/supplier/api/v1 SUPPLIER_API_KEY=mock \
PAWAPAY_API_URL=http://127.0.0.1:4010/pawapay/v2 PAWAPAY_API_TOKEN=mock npm run dev
# pilotage du mock : curl -X POST -d '{"payment":"completed","nextOrder":"delivery_failed"}' http://127.0.0.1:4010/_control
```

---

## Mise en production — checklist

1. `.env` de production complet (voir `.env.example`), `APP_URL` en `https://`, `NODE_ENV=production`. Le serveur refuse de démarrer si une clé obligatoire manque.
2. `npm run db:migrate` sur la base de production.
3. Orqex : clé `sk_live_…`, prestataire de paiement connecté, webhook `https://<domaine>/api/webhooks/orqex`.
4. Planificateur : interne par défaut (serveur permanent). Sinon `DISABLE_INTERNAL_SCHEDULER=1` et appeler `POST /api/cron/tick` chaque minute avec `Authorization: Bearer $CRON_SECRET`.
5. Compléter les mentions légales (`/mentions-legales`) et faire relire CGV / confidentialité par un juriste.
6. Recharger le solde du fournisseur (bot Telegram) et surveiller l'alerte « solde insuffisant ».
7. Sauvegarder `ENCRYPTION_KEY` hors du serveur : sa perte rend les accès déjà livrés illisibles.
