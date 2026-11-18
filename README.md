# Licences.pro — Abonnements & Licences Numériques en FCFA et Crypto

Plateforme e-commerce moderne et sécurisée spécialisée dans la vente d'abonnements premium, licences logicielles et accès numériques (Netflix, ChatGPT, Claude, Canva, Spotify, Duolingo, JetBrains, VPN, etc.).

Conçue avec **Laravel 11**, **Vue 3 (Composition API)**, **Inertia.js**, **TypeScript** et **Tailwind CSS**, la plateforme permet aux utilisateurs d'Afrique de l'Ouest/Centrale et de l'international de régler leurs accès en **FCFA (Mobile Money / Cartes)** ou en **Crypto-monnaies (USDT, BTC, SOL...)**, avec livraison instantanée dans leur espace sécurisé.

---

## 🌟 Fonctionnalités Principales

### 🛒 Boutique & Expérience Client
* **Catalogue dynamique (100+ offres)** : Filtrage instantané par catégorie (*IA et assistants*, *Création et design*, *Bureautique*, *Divertissement*, *VPN*, etc.), recherche textuelle, tri par prix et défilement infini (*Infinite Scroll*).
* **Carrousel 3D d'accueil & Produits Phares** : Mise en avant prioritaire des 10 grandes marques VIP (Netflix, Spotify, Prime Video, Claude, ChatGPT, Canva, CapCut, Apple Music, Apple TV, ElevenLabs).
* **Affichage transparent** : Durées déduites dynamiquement (*1 mois, 3 mois, 1 an...*), type d'accès clarifié (*Lien d'activation, Compte fourni, Code à copier*), prix nets en FCFA sans frais cachés.
* **Bilingue natif (FR / EN)** : Traduction complète et basculement instantané Français / Anglais sur toute la boutique et l'espace client.

### 💳 Passerelles de Paiement
* **Mobile Money & Cartes Bancaires (CinetPay)** :
  * Orange Money, MTN Mobile Money, Moov Money, Wave.
  * Cartes bancaires internationales (Visa, Mastercard).
* **Crypto-monnaies (Cryptomus via SDK officiel PHP)** :
  * USDT (réseaux économiques TRC20, BEP20, Polygon), Bitcoin, Solana, TON, Ethereum, Litecoin.
  * Conversion automatique FCFA ➔ USD/USDT selon le taux de change configurable.
  * Validation instantanée par webhook sécurisé avec signature cryptographique.
* **Portefeuille client (Wallet interne)** :
  * Rechargement de solde par Mobile Money ou Crypto.
  * Paiement en 1 clic sans redirection pour les clients réguliers.
  * Traçabilité comptable complète de chaque mouvement (*crédit, débit, commande liée*).
* **Reprise de paiement intelligente (`/paiement/reprendre`)** : Permet à l'utilisateur de finaliser une commande en attente sans devoir repasser toute la commande.

### 🔐 Sécurité & Confidentialité
* **Authentification sans mot de passe (*Passwordless*)** : Connexion ultra-rapide et sécurisée par code temporaire à usage unique envoyé par e-mail (aucun mot de passe stocké ni piratable).
* **Coffre-fort d'accès chiffré (AES-256-GCM)** : Les identifiants, liens et clés de licence livrés sont chiffrés en base de données et décryptés uniquement à la volée dans l'espace client sécurisé. Aucun identifiant n'est envoyé en clair dans les e-mails.
* **Headers HTTP de sécurité renforcés** : `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Strict-Transport-Security`.
* **Confidentialité totale des fournisseurs** : Masquage strict de tous les identifiants techniques, URLs d'API et messages d'erreurs du fournisseur grossiste.
* **Protection IDOR & Anti-tampering** : Vérification stricte des autorisations de chaque utilisateur sur ses commandes, tickets et transactions.

### ⚙️ Automatisation & Background Workers
* **Synchronisation Catalogue Fournisseur** : Gestion résiliente de la pagination, détection des ruptures de stock, index unique anti-doublons et calcul automatique des marges bénéficiaires.
* **Livraison automatique (*Fulfillment*)** : Déclenchement automatique de la commande grossiste et attribution de l'accès dès réception de la notification webhook de paiement (CinetPay ou Cryptomus).
* **Tâche planifiée (`cron:tick`)** : Exécutée chaque minute pour rattraper les commandes en attente, rafraîchir les stocks toutes les 5 minutes, traiter les alertes de stock et purger les codes expirés.

---

## 🛠️ Stack Technique

* **Backend** : PHP 8.2+, Laravel 11.x, Eloquent ORM, MySQL 8.0+ / SQLite.
* **Frontend** : Vue 3 (Composition API avec `<script setup>`), Inertia.js, TypeScript.
* **Design & Styles** : Tailwind CSS, Lucide Icons, Simple Icons (logos de marques en SVG).
* **Paiements** : SDK officiel `cryptomus/api-php-sdk`, passerelle CinetPay.
* **Build tool** : Vite 8.x.
* **Tests** : PHPUnit 11 (63 tests automatisés, 378 assertions).

---

## 🚀 Installation & Développement Local

### Prérequis
* PHP >= 8.2 (extensions : `curl`, `mbstring`, `pdo_mysql`, `openssl`, `bcmath`)
* Composer >= 2.x
* Node.js >= 18.x & npm
* MySQL ou SQLite

### 1. Cloner le projet
```bash
git clone https://github.com/maarazak/licences.git
cd licences
```

### 2. Installer les dépendances
```bash
# Dépendances PHP
composer install

# Dépendances JavaScript
npm install
```

### 3. Configurer l'environnement
```bash
cp .env.example .env
php artisan key:generate
```

Éditez le fichier `.env` avec vos accès :
```ini
APP_NAME="Licences.pro"
APP_URL=http://localhost:8000

# Base de données
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=licences_laravel
DB_USERNAME=root
DB_PASSWORD=

# Passerelle Fournisseur Grossiste
SUPPLIER_API_URL=https://flowseller-api.pe-supplykh.com
SUPPLIER_API_KEY=votre_cle_fournisseur

# Passerelle Mobile Money (CinetPay)
CINETPAY_API_KEY=votre_cle_cinetpay
CINETPAY_API_PASSWORD=votre_mot_de_passe_cinetpay
CINETPAY_ENVIRONMENT=sandbox # ou production

# Passerelle Crypto (Cryptomus)
CRYPTOMUS_MERCHANT_ID=votre_merchant_uuid
CRYPTOMUS_PAYMENT_KEY=votre_payment_key
CRYPTOMUS_PAYOUT_KEY=votre_payout_key # optionnel
CRYPTOMUS_API_URL=https://api.cryptomus.com/v1

# Expédition des e-mails
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="support@licences.pro"
MAIL_FROM_NAME="Licences.pro"
```

### 4. Migrations et Données Initiales
```bash
php artisan migrate
php artisan catalog:sync
```

### 5. Lancer l'environnement de développement
```bash
# Terminal 1 : Serveur Laravel
php artisan serve

# Terminal 2 : Serveur Vite (Hot Reload)
npm run dev
```

L'application est accessible sur `http://localhost:8000`.

---

## 🧪 Tests Automatisés

Le projet dispose d'une couverture de tests rigoureuse vérifiant les paiements, les webhooks, le chiffrement, les flux de commandes et la sécurité :

```bash
php artisan test
```

Résultat : **63 tests passés avec succès (378 assertions)**.

---

## ⏱️ Tâches Planifiées (Cron)

En production, configurez le cron système pour faire tourner le planificateur Laravel toutes les minutes :

```bash
* * * * * cd /chemin/vers/votre/projet && php artisan schedule:run >> /dev/null 2>&1
```

Vous pouvez aussi tester manuellement la tâche d'arrière-plan via :
```bash
php artisan cron:tick
```

---

## 📦 Compilation de Production

Pour construire les assets optimisés pour la production :
```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 📄 Licence

Ce projet est sous licence propriétaire. Tous droits réservés.
