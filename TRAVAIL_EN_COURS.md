# Documentation & Suivi — Licences Laravel (Vue.js 3 + Inertia.js + Blade)

Projet situé dans `/Users/maar/MyData/Projets/licences-laravel`.
Migration complète effectuée avec succès : **Next.js a été intégralement remplacé par Vue.js 3 + Inertia.js**.
Le projet est un monolithe moderne 100 % compatible avec un **hébergement mutualisé PHP classique** (aucun serveur Node.js en production).

---

## 1. Nouvelle Architecture Monolithique

```text
licences-laravel/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/         # Backoffice Blade (Dashboard, Commandes, Produits, Clients, etc.)
│   │   │   ├── Auth/          # ClientAuthController (Inertia - Code e-mail) & Admin Auth
│   │   │   ├── Shop/          # HomeController, CatalogController, CheckoutController, PageController
│   │   │   ├── Account/       # AccountController (Dashboard, Achats, Solde, Tickets, Alertes, Profil)
│   │   │   └── Api/           # API REST (Catalog, Orders, Wallet, Webhooks)
│   │   └── Middleware/
│   │       └── HandleInertiaRequests.php  # Partage props Inertia (auth, flash, devises)
│   ├── Models/                # Modèles Eloquent avec UUIDs (User, Order, Product, Ticket, etc.)
│   └── Services/              # PawaPayService (Mobile Money v2) & CryptoService (AES-256-GCM)
│
├── database/                  # Migrations & Tables Spatie Permission avec UUID morphs
├── resources/
│   ├── views/
│   │   ├── admin/             # Vues Blade du Backoffice administratif (/admin/*)
│   │   ├── layouts/admin.blade.php # Layout maître Backoffice avec SweetAlert2
│   │   └── app.blade.php      # Layout maître racine Inertia.js (Boutique & Espace client)
│   ├── css/
│   │   └── app.css            # Styles Tailwind CSS, animations, classes design tokens
│   └── js/
│       ├── app.js             # Initialisation Inertia + Vue 3 + Pinia + Ziggy
│       ├── bootstrap.js       # Axios & en-têtes CSRF
│       ├── Layouts/
│       │   ├── SiteLayout.vue # Header sticky, recherche, menu compte, footer, nav mobile
│       │   └── AccountLayout.vue # Barre latérale compte client, solde, raccourcis
│       ├── Pages/
│       │   ├── Shop/          # Home.vue, Index.vue, Show.vue, HowItWorks.vue, Support.vue, Terms.vue, Privacy.vue, Legal.vue
│       │   ├── Checkout/      # Validation.vue (panier/stepper/Mobile Money), Return.vue (statut pawaPay)
│       │   ├── Auth/          # Login.vue (e-mail + code 6 chiffres)
│       │   ├── Account/       # Dashboard.vue, Orders.vue, OrderShow.vue (révélation accès chiffrés), Wallet.vue, Tickets.vue, TicketCreate.vue, TicketShow.vue, Alerts.vue, Profile.vue
│       │   └── Errors/        # 404.vue
│       ├── Components/
│       │   ├── Shop/          # ProductCard.vue (badge stock, prix FCFA, logo proéminent)
│       │   ├── Shared/        # BrandLogo.vue (logos marques & monogrammes), PaymentLogos.vue
│       │   └── Navigation/    # LogoLockup.vue
│       ├── Types/             # enums.ts (statuts commandes, tickets, types de livraison)
│       └── Utils/             # countries.ts (UEMOA/CEMAC), brands.ts, format.ts, money.ts, site.ts
│
├── public/
│   ├── brands/                # Logos PNG/JPG des services (Canva, ChatGPT, Netflix, Prime, etc.)
│   ├── payments/              # Logos Mobile Money (Orange Money, Moov, Wave, USDT)
│   └── build/                 # Fichiers compilés par Vite pour la production
│
├── routes/
│   ├── web.php                # 69 routes web (Boutique Vue 3, Espace client Vue 3, Backoffice Blade)
│   └── api.php                # Endpoints API REST pour intégrations et webhooks
├── composer.json              # Laravel 13, Inertia Laravel, Spatie Permission, pawaPay SDK, Ziggy
├── package.json               # Vue 3, Inertia Vue3, Pinia, Tailwind CSS, Lucide, SweetAlert2
└── vite.config.js             # Configuration Vite avec @vitejs/plugin-vue et alias @
```

---

## 2. Fonctionnalités Migrées et Préservées à 100 %

### A. Boutique et Site Public (Vue 3 + Inertia)
- **Accueil (`/`)** : Héros dynamique, logos de paiement flottants, rayons, sélection d'abonnements phares, 3 étapes de commande, garanties et accordéon FAQ.
- **Boutique (`/boutique`)** : Recherche en temps réel, filtres par catégorie (Streaming, IA, Pro, VPN, etc.), cartes produits réactives avec calcul dynamique du prix FCFA.
- **Fiche Produit (`/boutique/{slug}`)** : Logo de marque, caractéristiques (durée, type d'accès, garantie), stock en direct, instructions et produits similaires.
- **Pages institutionnelles** : Comment ça marche (`/comment-ca-marche`), Assistance & FAQ (`/assistance`), CGV (`/conditions`), Confidentialité (`/confidentialite`), Mentions légales (`/mentions-legales`).

### B. Commande & Paiement Mobile Money (pawaPay v2)
- **Validation (`/commande/validation?produit=slug`)** : Sélecteur de quantité avec recalcul immédiat du montant FCFA, coordonnées client, sélecteur de pays avec indicatif et masque de téléphone, choix entre **Mobile Money (pawaPay)** et **Solde du compte**.
- **Page de retour pawaPay (`/paiement/retour`)** : Statut en direct (validé, en cours, échoué), synchronisation avec pawaPay et liens d'accès.
- **Reprise de paiement (`/paiement/reprendre?commande=id`)** : Régénération automatique d'URL de session pawaPay sécurisée en cas d'expiration du lien CloudFront.

### C. Espace Client Sécurisé (Vue 3 + Inertia)
- **Authentification sans mot de passe (`/connexion`)** : Saisie d'e-mail -> Envoi d'un code unique à 6 chiffres par e-mail -> Vérification HMAC avec protection contre les attaques par force brute (limite à 5 essais) -> Connexion sur le guard `web`.
- **Tableau de bord (`/compte`)** : Résumé du solde FCFA, compteur de commandes, alertes et tickets ouverts.
- **Mes achats (`/compte/achats`)** : Historique paginé des commandes avec statuts clairs (Payée, Livrée, En attente, Remboursée).
- **Détail d'une commande (`/compte/achats/{id}`)** : **Révélation des accès chiffrés AES-256-GCM** (comptes, mots de passe, liens d'activation), bouton de copie dans le presse-papiers avec toast SweetAlert2, journal de commande et lien direct pour ouvrir un ticket.
- **Portefeuille & Solde (`/compte/solde`)** : Formulaire de recharge par Mobile Money (montants rapides ou montant libre en FCFA) et historique complet des crédits/débits.
- **Assistance & Tickets (`/compte/assistance`)** : Liste des tickets, création de ticket lié à une commande (`/nouveau`), messagerie conversationnelle client/support avec notifications.
- **Alertes de réapprovisionnement (`/compte/alertes`)** : Liste et suppression des alertes e-mail.
- **Profil (`/compte/profil`)** : Mise à jour du pays par défaut, numéro Mobile Money et langue.

### D. Backoffice Administrateur en Blade (Intact & Cloisonné)
- Accès indépendant sur `/admin/connexion` via le guard `admin` (`auth:admin` + Spatie `role:admin`).
- **Isolation totale des sessions** : Vous pouvez être connecté en Client sur la boutique et en Administrateur sur le backoffice dans le même navigateur sans aucun conflit.
- Gestion complète : Tableau de bord financier, Commandes (remboursement solde, passerelle, notes internes), Produits (prix FCFA, rang, publication), Clients (ajustement solde, blocage), Tickets, Journal d'audit et Paramètres.

---

## 3. Commandes de Développement

```bash
# 1. Lancer le serveur backend Laravel (Port 8000)
php artisan serve

# 2. Dans un terminal séparé : lancer Vite en mode développement
npm run dev
```

- **Site public & Boutique** : `http://localhost:8000`
- **Backoffice Admin Blade** : `http://localhost:8000/admin`
  - E-mail : `abdoulrazakmaali@gmail.com`
  - Mot de passe : `Admin123!`

---

## 4. Tests et Validation

- **Tests unitaires et fonctionnels PHPUnit** :
  ```bash
  php artisan test
  ```
  **Résultat : 9/9 tests passés avec 89 assertions validées avec succès.**
  (Vérification des routes Inertia, catalogue, fiche produit, demande de code client, pages légales et isolation du backoffice Blade).

- **Compilation frontend Vite** :
  ```bash
  npm run build
  ```
  **Résultat : Compilation propre en ~590ms dans `public/build/` sans aucune erreur.**

---

## 5. Procédure de Déploiement sur Hébergement Mutualisé (cPanel, O2switch, Hostinger, OVH)

Puisque Next.js a été remplacé par Vue.js 3 compilé via Vite, **aucun serveur Node.js permanent n'est requis en production**.

1. **Compiler les assets localement avant déploiement** :
   ```bash
   npm run build
   ```
   *(Cela génère le dossier `public/build/` avec tous les bundles JS et CSS optimisés)*

2. **Transférer les fichiers sur le serveur mutualisé** :
   - Déposez l'ensemble du projet dans votre espace FTP/SSH (ex : `home/username/licences-laravel`).
   - Pointez le document root de votre domaine ou `public_html` vers le sous-dossier `public/` de Laravel :
     ```text
     DocumentRoot: /home/username/licences-laravel/public
     ```

3. **Configurer le fichier `.env` sur le serveur** :
   - `APP_ENV=production` et `APP_DEBUG=false`
   - `APP_URL=https://votredomaine.com`
   - Informations de votre base MySQL mutualisée (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)
   - Clés `PAWAPAY_API_URL` et `PAWAPAY_API_TOKEN`

4. **Optimisations Laravel en production** :
   Exécutez via SSH (ou terminal cPanel) :
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
   *L'application s'exécute alors à 100 % en PHP/MySQL via Apache/LiteSpeed avec des performances maximales !*
