# Travail en cours — Licences, boutique de licences numériques (FCFA)

Projet démarré le 2026-11-11 : clone fonctionnel de getpass.shop (vitrine, espace client, backoffice), tout en Next.js. Fournisseur : API Flowseller. Paiement : pawaPay v2 (agrégateur Mobile Money). Base : MySQL (DBngin).

## État au 2026-11-12 (mis à jour en fin de journée)

### Fait et vérifié (parcours testés en local avec un faux fournisseur et un faux Orqex)
- **Vitrine** : accueil, boutique (recherche, catégorie, disponibilité, tri), fiche produit, devise d'affichage XOF/XAF/USD, pages « Comment ça marche », assistance (FAQ), CGV, confidentialité, mentions légales, 404, page d'erreur.
- **Connexion** par code e-mail à 6 chiffres (expire en 10 min, 5 essais, usage unique, limitation de débit), sessions en base, déconnexion de tous les appareils.
- **Achat** : validation (coordonnées, pays, quantité, e-mails d'activation si requis, conditions), vérification en direct du produit, du prix (jamais sous le coût) et du solde fournisseur AVANT d'encaisser, session Orqex, page de retour qui relit l'état réel du paiement.
- **Confirmation de paiement** : webhook + retour client + tâche planifiée convergent vers `syncPayment()` ; contrôle de l'identifiant et du montant ; rejeu sans effet (un seul achat fournisseur).
- **Livraison** : achat fournisseur idempotent, accès chiffrés AES-256-GCM, activation manuelle suivie par interrogation, reprise automatique après coupure.
- **Échec de livraison** : le client choisit un **avoir** (immédiat, sur son solde) ou un **remboursement** (traité par l'équipe). Paiement possible avec le solde (commande entière).
- **Espace client** : tableau de bord, achats (filtres, recherche), détail de commande, accès (copie en un clic), solde + historique, alertes de retour en stock, assistance (demandes + fil de messages), paramètres.
- **Backoffice** : tableau de bord (ventes, marge, solde fournisseur, alertes), commandes (relancer, créditer, marquer remboursée, revérifier le paiement, note interne, historique), produits (synchro, publier, prix fixe/marge, catégorie, durée, mode d'emploi), clients (suspendre, ajuster le solde), assistance (répondre, clôturer), paramètres (taux, marge, arrondi, bandeau), journal des actions.
- **Sécurité** : CSP avec nonce, en-têtes (HSTS, X-Frame-Options…), cookies `__Host-` en production, zod partout, rate limiting en base, droits revérifiés dans chaque action, redirections internes uniquement.
- **Tests** : 13 tests vitest (prix, formats), build de production OK, `tsc` et `eslint` sans erreur.
- **Clé fournisseur** renseignée et testée en lecture : 100 produits lus, solde du compte revendeur à 0 $.
- **SMTP Brevo** renseigné : authentification vérifiée (aucun e-mail encore envoyé).

### Ajouts de fin de journée
- **Relances par e-mail des paiements non terminés** (2 au maximum par commande, voir `CLAUDE.md`), désactivables par le client dans Paramètres ; testées (session ouverte, expirée, offre en rupture, commande trop récente, limite de 6 h, idempotence).
- **Logo « Licences »** (monogramme « L » en ticket aux couleurs du thème), favicon, nom de boutique « Licences ».
- **Fidélité à getpass.shop** : accueil avec carrousel d'offres (lecture auto, pause, flèches, points) et bande de trois points ; fiche produit avec « Autres formules » et « Voir toutes les conditions ».
- **Revue de sécurité par un modèle plus puissant (Opus)**, 12 constats, tous traités : paiement arrivé après expiration (repris et livré), double achat fournisseur sur issue incertaine (même clé d'idempotence, fenêtre 23 h, client sans choix automatique), double dédommagement avoir + remboursement (verrouillé), pages admin protégées une à une, vente à perte si le coût fournisseur flambe, total attendu vérifié, adresse IP non falsifiable (`x-real-ip` / dernière entrée XFF), plafond quotidien de codes par compte, alertes de stock, validation tolérante du fournisseur, relectures Orqex limitées, CSP `form-action` pour Orqex, alertes admin dédoublonnées.

### Évolutions du 2026-11-12 (soir)
- **Thème clair** (fini le sombre), **vraies icônes** (`lucide-react`), aucun émoji ; textes courts et commerciaux ; **produits phares en premier** (Netflix, Claude, ChatGPT, Canva, CapCut, Google AI Plus, Prime Video, Microsoft 365, Adobe Express — rang réglable dans le backoffice) avec noms propres et descriptions FR/EN.
- **Français / anglais** sur tout le site client et les e-mails ; sélecteur dans l'en-tête et Paramètres.
- **FCFA / dollar** avec taux de change réel (BCE + parité fixe du FCFA) ; paiement toujours en FCFA.
- **Logos** : 14 vrais logos téléchargés dans `public/brands/` (HMA reste en monogramme : icône trop petite).
- Les produits phares dépendent du stock du fournisseur : ceux en rupture (Claude, Canva, CapCut, Prime Video, Microsoft 365 à certains moments) disparaissent de l'accueil et reviennent seuls.

### Refonte du design (2026-11-12)
Accueil (héros sombre, carrousel d'offres qui glisse, bandeau de marques défilant, rayons, étapes, réassurance), boutique (recherche, puces de catégories), fiche produit, en-tête/pied de page, cartes produit avec logos de marque. **Pages encore à l'ancien gabarit** (elles héritent des nouveaux boutons/cartes mais n'ont pas été redessinées une à une) : validation de commande, espace client, backoffice, connexion, pages légales. **Affichage mobile non vérifié visuellement.**
- Logos : pictogrammes libres pour ~30 marques ; **monogrammes colorés** pour Canva, CapCut, ChatGPT/OpenAI, Adobe, Prime Video, Microsoft, LinkedIn, Scribd, Lovable, Gamma, Granola, Arize, Shahid, HMA, Pangram, Muse, Mobbin, Magic Patterns (absents de la bibliothèque libre). À remplacer en déposant les fichiers dans `public/brands/`.

### Non fait (écarts connus avec getpass.shop)
- **Bascule de langue FR/EN** : le site est en français uniquement.
- **Connexion Google** : code e-mail uniquement (nécessite un identifiant client Google Cloud).
- **Remboursement automatique** via Orqex : l'admin le fait dans le tableau de bord Orqex puis l'enregistre.

### À faire avant l'ouverture au public
1. **pawaPay** : configurer votre compte sur le dashboard pawaPay (`https://dashboard.pawapay.io`), renseigner `PAWAPAY_API_TOKEN` dans `.env`, déclarer l'URL de webhook de dépôt `https://<domaine>/api/webhooks/pawapay`. Tester en Sandbox (`https://api.sandbox.pawapay.io/v2`).
2. **`MAIL_FROM`** : mettre une adresse d'expéditeur validée dans Brevo (actuellement `no-reply@exemple.com`, qui sera refusée). Renseigner `SUPPORT_EMAIL`.
3. **Recharger le solde fournisseur** (bot Telegram). Tant qu'il est à 0 $, toute vente est refusée avant paiement — c'est voulu.
4. **Catalogue** : lancer « Synchroniser » dans le backoffice (100 produits), puis régler pour chaque produit publié : catégorie boutique, durée, nom affiché, mode d'emploi. Les textes du fournisseur sont en anglais et ne sont affichés qu'avec les accès livrés.
5. **Prix** : régler le taux USD→FCFA (défaut 620) en tenant compte des frais de recharge du fournisseur et des frais Orqex/prestataire, puis la marge.
6. **Textes légaux** : compléter `/mentions-legales` (raison sociale, RCCM/IFU, adresse, hébergeur) ; faire relire CGV et confidentialité.
7. **Nom de la boutique** : `NEXT_PUBLIC_APP_NAME` (actuellement « Ma Boutique »), logo, couleurs.
8. **Hébergement** : choisir (Vercel ou VPS) ; base MySQL de production ; planificateur `POST /api/cron/tick` chaque minute.
9. **Marge minimale** : `coversCost` accepte une marge nulle ; prévoir une marge couvrant les frais Orqex/prestataire (réglage « Marge générale »).
10. **Test réel du paiement** : une commande de test en sandbox Orqex, puis une vraie petite commande, avant d'ouvrir.

### Décisions et écarts par rapport à getpass.shop
- Connexion Google non reprise (code e-mail uniquement) ; peut s'ajouter plus tard.
- Les identifiants achetés ne sont **pas envoyés par e-mail** : lien vers « Mes achats » (plus sûr).
- Le champ `category` du fournisseur décrit en réalité le type d'accès (`accounts`, `link`, `code`) ; la catégorie boutique (rayon) est un champ séparé, à renseigner dans le backoffice.
- Produits « sur commande » (`preorder`) : non vendables (le fournisseur refuse via l'API).
- Pas de remboursement automatique via Orqex : l'admin émet le remboursement dans le tableau de bord Orqex puis l'enregistre dans le backoffice.

### Données de test locales
- 3 produits `[DEMO]` insérés dans la base locale (identifiants `demo-1..3`) : à supprimer avant la mise en production (`DELETE FROM products WHERE supplier_id LIKE 'demo-%'`) ou les masquer. Quelques commandes de test existent aussi.
- Compte administrateur : créé par `npm run db:seed` (seeder `database/seeders/01-admin.mjs`), connexion sur `/admin/connexion`.

## Journal
- 2026-11-11 : analyse de getpass.shop (Next.js sur Vercel), lecture des API Flowseller et Orqex, création du projet, schéma, services, pages.
- 2026-11-12 : espace client complet (solde, alertes, avoir/remboursement), backoffice, tests, adaptation aux vraies données du fournisseur, `CLAUDE.md`, revue de sécurité confiée à un modèle plus puissant.

## Session du 12 novembre 2026 — audits et corrections

- Fait : connexion admin séparée (`/admin/connexion`, e-mail + mot de passe scrypt, seeder `npm run db:seed`, plus de `ADMIN_EMAILS`) ; données de démo supprimées ; jeton anti double-clic (`orders.client_token`) ; relecture Orqex des commandes closes et avant la relance n°2 ; avoir/relance bloqués si remboursement demandé ; « Mettre en échec » + délai 72 h d'activation ; IP de confiance (`TRUSTED_IP_HEADER`) ; plafonds de connexion revus ; AAD sur le chiffrement des accès (format v2) ; `datetime(3)` pour les journaux ; migrations 0005 à 0007 ; 68 tests passent (`licences_test` à migrer à la main : `drizzle-kit migrate` ne la touche pas).
- Reste (faible priorité, audits) : dates/montants au format anglais en EN, pagination des listes admin (>300), file d'envoi d'e-mails (`mail_outbox`), carrousel (pause au focus), titres SEO/sitemap, `after()` pour la synchro catalogue/taux, e-mail Brevo : valider l'expéditeur, mot de passe admin à créer (`! npm run db:seed`).

## Catalogue en base + tâche planifiée interne (12 novembre 2026)
- Visiteurs : plus aucune synchronisation à la consultation (`ensureFreshCatalog` supprimé) ; pages = lecture de la base uniquement.
- `syncCatalog` incrémentale (rapport created/updated/unchanged/deactivated) ; erreur mémorisée (`catalogSyncError`) et affichée sur le tableau de bord.
- `services/scheduler.ts` : `runTick` (corps de l'ancienne route cron) + `startScheduler` (chaque minute, démarré par `instrumentation.ts`, coupé par `DISABLE_INTERNAL_SCHEDULER=1`). Route `/api/cron/tick` conservée.
- Lecture fournisseur (GET) retentée une fois ; messages réseau en français.
- SweetAlert2 (confirmations, notifications, boîte de traitement) et cercles de chargement (boutons, loading.tsx).
- Pas de multi-fournisseurs ni de stock en gros pour l'instant (décision du 12/11).

- Taux du dollar : taux réel BCE retiré (fx.ts supprimé). Un seul taux `usdRate` (669) partout, y compris l'affichage en dollars du site (« Taux du jour »).

- Synchro : le fournisseur annonce à tort « stock 0 / non vendable » par moments (26 produits). Confirmation par 5 lectures consécutives avant d'enregistrer une dégradation (migration 0008, `applyReading`).

- Double authentification admin par e-mail (otp.ts `issueAdminChallenge`/`verifyAdminChallenge`, `auth/admin-2fa.ts`, `adminVerifyCodeAction`). Contrôle de secrets avant commit (`scripts/check-secrets.mjs`, `.githooks/pre-commit`, `npm run check:secrets`).
