# Travail en cours — Licences Laravel (Boutique de licences numériques en FCFA)

Projet créé le 2026-11-17 dans `/Users/maar/MyData/Projets/licences-laravel`.
Architecture hybride :
- **Backoffice Admin** : Laravel (PHP 8.4 via Laravel Herd) avec vues **Blade** et contrôleurs dédiés.
- **Site Public & Espace Client** : **Next.js** (App Router, Tailwind CSS) dans le dossier `frontend/`.
- **Base de données dédiée** : `licences_laravel` (MySQL `127.0.0.1:3306`, utilisateur `root`, sans mot de passe).

---

## 1. Architecture et Répartition

```
licences-laravel/
├── app/                      # Contrôleurs, Modèles et Services Laravel
│   ├── Http/Controllers/Admin/ # Contrôleurs du backoffice Blade (Commandes, Produits, etc.)
│   └── Models/               # Modèles Eloquent (Order, Product, User, etc.)
├── database/                 # Migrations et seeders Laravel
├── resources/views/admin/    # Vues Blade du backoffice (/admin)
├── routes/web.php            # Routes Laravel (Backoffice Blade & endpoints admin)
├── .env                      # Configuration Laravel (DB: licences_laravel, pawaPay, SMTP)
└── frontend/                 # Application Next.js (Site public, boutique & espace client)
    ├── src/app/(site)/       # Boutique (/boutique), Panier, Commande, Espace client (/compte)
    ├── src/server/           # Services backend pawaPay, orders, wallet, auth
    ├── .env                  # Configuration Next.js (DATABASE_URL=.../licences_laravel)
    └── package.json          # Dépendances Next.js
```

---

## 2. Base de données (`licences_laravel`)

La base de données `licences_laravel` est indépendante de la base d'origine `licences`.
Toutes les tables ont été clonées et initialisées :
- `users` : comptes clients (authentification par code e-mail, solde FCFA, pays, langue).
- `orders` : commandes passées, statuts, montants FCFA, identifiants de transaction pawaPay (`depositId`), livraisons chiffrées AES-256.
- `order_events` : historique et journal d'audit de chaque commande.
- `products` : catalogue d'abonnements et licences numériques (ChatGPT, Claude, Canva, CapCut, Prime Video, etc.).
- `wallet_deposits` : recharges de solde client par Mobile Money via pawaPay.
- `wallet_entries` : mouvements de débit/crédit du solde client.
- `stock_alerts` : notifications e-mail lors du réapprovisionnement.
- `tickets` & `ticket_messages` : assistance et réclamations clients.
- `settings` : réglages généraux (taux USD/FCFA, marges, mode maintenance).
- `sessions`, `login_codes`, `rate_limits` : gestion de session et sécurité.

---

## 3. Paiement Mobile Money & Résilience

Le système intègre l'API Marchand **pawaPay v2** :
- **100 % Mobile Money** : Orange Money, Moov Money, MTN MoMo, Wave.
- **Double zone Franc CFA (parité 1:1)** :
  - **Zone UEMOA** : `XOF` (Burkina Faso, Côte d'Ivoire, Sénégal, Bénin).
  - **Zone CEMAC** : `XAF` (Cameroun, Congo-Brazzaville, Gabon).
- **Résilience automatique** :
  - Si le numéro saisi par le client comporte un format inhabituel ou un opérateur non supporté, la session pawaPay est automatiquement relancée sans le numéro afin d'afficher la sélection directe d'opérateur sur la page sécurisée.
  - Si le pays sélectionné est hors des zones actives, le processeur bascule en toute transparence sur `BFA` (`XOF`) sans bloquer le client.
  - Renouvellement automatique des URLs de paiement pawaPay / CloudFront expirées (`/paiement/reprendre`).

---

## 4. Démarrage des Services en Développement

### Commande unique (Recommandée)
Depuis la racine du projet `/Users/maar/MyData/Projets/licences-laravel` :
```bash
npm run dev
```
Cette commande lance simultanément via `concurrently` :
- Le serveur **Laravel** (Backoffice Blade & API) sur `http://localhost:8000`
- Le serveur **Next.js** (Boutique & Espace Client) sur `http://localhost:3001`

### Ou commandes séparées :
- **Backoffice Laravel** :
  ```bash
  npm run dev:laravel
  # ou : php artisan serve --port=8000
  ```
  Accès Backoffice Blade : `http://localhost:8000/admin` (Identifiants : `abdoulrazakmaali@gmail.com` / `Admin123!`)
- **Frontend Next.js** :
  ```bash
  npm run dev:frontend
  # ou : cd frontend && npm run dev -- -p 3001
  ```
  Accès Site client : `http://localhost:3001`

---

## 5. Sécurité, Rôles et Isolation de Session

1. **Isolation stricte des sessions** :
   - Le cookie de session admin Laravel est nommé `licences_admin_session` (configuré dans `.env`).
   - Le frontend client Next.js utilise son propre cookie de session sécurisé.
   - Les deux sessions peuvent coexister simultanément sur le même navigateur sans aucun conflit.

2. **Rôles et Permissions (Spatie)** :
   - Le package `spatie/laravel-permission` (v8.5.0) est installé avec support des clés primaires UUID (`model_morph_key` en UUID).
   - Les rôles `admin` et `client` sont initialisés dans la base `licences_laravel`.
   - Les routes d'administration `/admin/*` sont protégées par le middleware `['auth', 'role:admin']`.

3. **Modales et Confirmations SweetAlert2** :
   - Intégration de SweetAlert2 dans `resources/views/layouts/admin.blade.php`.
   - Confirmations interactives sur le remboursement solde, remboursement passerelle, et blocage/déblocage client.
   - Toasts automatiques sur les messages flash `session('success')` et `session('error')`.

---

## 6. État des Tests et Qualité

- **Frontend Next.js** (`licences-laravel/frontend`) :
  - `npm test` : **123/123 tests passés avec succès** (services orders, wallet-deposits, pawapay, pricing, format, i18n, etc.).
  - Connexion validée sur la base MySQL `licences_laravel`.
- **Laravel Backend** (`licences-laravel`) :
  - `php artisan db:show` : validé sur `licences_laravel`.
  - Contrôleurs Blade (Tableau de bord, Commandes, Produits, Clients, Assistance, Journal, Paramètres) validés.
  - API Laravel (`/api/catalog`, `/api/orders`, `/api/wallet`, `/api/webhooks`) testée et opérationnelle.
