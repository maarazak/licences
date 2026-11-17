# Suivi de Migration & Audit de Conformité — Licences Laravel

**Dernière mise à jour** : 17 Novembre 2026  
**Projet source** : `/Users/maar/MyData/Projets/licences` (Laravel Backend + Next.js Frontend)  
**Projet cible** : `/Users/maar/MyData/Projets/licences-laravel` (Laravel Backend + Vue.js 3 / Inertia.js + Blade Backoffice)

---

## 1. Architecture Modulaire & Isolation Production / Local

Inspirée des standards de haute qualité appliqués sur `livreurcravate-web` :

### A. Données obligatoires en tout environnement (Production & Local)
- **`PermissionSeeder.php`** : Définition des permissions Spatie (`manage products`, `manage orders`, `adjust balances`, etc.) pour les guards `admin` et `web`.
- **`RoleSeeder.php`** : Rôles Spatie `admin` et `client` avec assignation des permissions.
- **`SettingSeeder.php`** : Paramètres système réels (`usd_rate: 620`, `default_margin_percent: 25`, adresses de contact).
- **`AdminSeeder.php`** : Création du compte administrateur principal (`abdoulrazakmaali@gmail.com`).

### B. Données de démonstration (LOCAL et TESTING UNIQUEMENT)
- **`CustomerSeeder.php`** : Ne s'exécute **JAMAIS** en production (`if (!app()->environment('local', 'testing')) return;`). Crée le client de test `test@example.com` (solde crédité de 25 000 FCFA) et l'administrateur secondaire de développement.
- **`ProductSeeder.php`** : Ne s'exécute **JAMAIS** en production. Protégé par double garde. En production, les produits proviennent exclusivement de l'API revendeur Flowseller via `php artisan catalog:sync` ou de la création manuelle par l'administrateur dans le backoffice.

---

## 2. Configuration SMTP & Service d'E-mails Transactionnels

- **Même Fournisseur & Relais SMTP** : Brevo (`smtp-relay.brevo.com:587`, TLS avec identifiants officiels du projet `licences`).
- **Expéditeur Configuré** : `Licences <abdoulrazakmaali@gmail.com>`.
- **Service Dédié** (`app/Services/MailService.php`) :
  - Respecte le gabarit HTML/Texte officiel (couleurs `#0d5c4a`, bouton `#ff6a13`, pied de sécurité).
  - Événements gérés :
    1. Code de connexion magique client (`sendLoginCode`)
    2. Confirmation de paiement reçu (`sendOrderPaid`)
    3. Livraison des accès / licences (`sendOrderDelivered`)
    4. Activation manuelle en cours (`sendOrderActivation`)
    5. Commande retardée / assistance (`sendOrderDelayed`)
    6. Remboursement validé (`sendOrderRefunded`)
    7. Alerte de réassort (`sendStockAlert`)
    8. Réponse au ticket de support (`sendTicketReply`)
  - **Test d'envoi réel** : Validé avec succès via Brevo SMTP vers `abdoulrazakmaali@gmail.com` (Code retour `YES`).

---

## 3. Automatisation et Services Fournisseur

- **`app/Services/FlowsellerService.php`** : Client API revendeur Flowseller (catalogue, solde, achat, vérification statut).
- **`app/Services/OrderFulfillmentService.php`** : Traitement de la livraison automatique, chiffrement AES-256-GCM des accès via `CryptoService`, synchronisation pawaPay et maintenance.
- **`php artisan orders:maintain` (`MaintainOrdersCommand`)** : Rattrapage automatique des webhooks, expiration des commandes impayées >48h, suivi des activations (planifiée toutes les 5 minutes).
- **`php artisan catalog:sync` (`SyncCatalogCommand`)** : Synchronisation du catalogue revendeur en production et en local.

---

## 4. Résultats des Tests & Compilation

- **Exécution `php artisan migrate:fresh --seed` (Local)** : Succès avec chargement des données de test.
- **Exécution `APP_ENV=production php artisan migrate:fresh --seed --force` (Production)** : Succès strict — **aucune donnée de démo insérée**.
- **Suite de Tests PHPUnit (`php artisan test`)** : 12 tests réussis, 105 assertions, 0 échec.
- **Compilation Vite (`npm run build`)** : 42 assets compilés en 653 ms dans `public/build/`.
