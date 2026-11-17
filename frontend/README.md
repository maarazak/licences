# Boutique de licences numériques (FCFA)

Vitrine, espace client et backoffice en Next.js. Fournisseur : Flowseller. Paiement : Orqex. Base : MySQL.

Documentation de travail : `CLAUDE.md` (architecture, règles de sécurité, commandes) et `TRAVAIL_EN_COURS.md` (état d'avancement, à faire).

## Démarrage

```bash
npm install
cp .env.example .env     # puis renseigner les clés ; `npm run secrets` génère les secrets internes
npm run db:migrate       # crée les tables (MySQL démarré, base `licences` créée)
npm run dev              # http://localhost:3000
```
