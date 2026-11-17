<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { SITE_NAME } from '@/Utils/site';
</script>

<template>
  <SiteLayout>
    <Head title="Politique de Confidentialité" />

    <div class="container-page max-w-3xl py-12 space-y-8">
      <div>
        <h1 class="h1">Politique de Confidentialité</h1>
        <p class="text-xs text-slate-400 mt-2">Dernière mise à jour : 12 novembre 2026</p>
      </div>

      <div class="card p-6 sm:p-8 space-y-6 shadow-card text-xs sm:text-sm text-slate-600 leading-relaxed">
        <section class="space-y-2">
          <h2 class="h3 text-base text-slate-900">1. Données collectées</h2>
          <p>
            Dans le cadre de l'utilisation de {{ SITE_NAME }}, nous collectons uniquement les informations indispensables au traitement de vos commandes : votre adresse e-mail (authentification et livraison), votre numéro de téléphone Mobile Money (transmission à la passerelle de paiement) et vos historiques de commandes.
          </p>
        </section>

        <section class="space-y-2">
          <h2 class="h3 text-base text-slate-900">2. Sécurité des accès et chiffrement</h2>
          <p>
            Vos identifiants et licences livrés sont chiffrés avec l'algorithme AES-256-GCM. Aucun membre de notre personnel ne peut consulter vos clés en clair sans une procédure d'assistance sollicitée par vous-même.
          </p>
        </section>

        <section class="space-y-2">
          <h2 class="h3 text-base text-slate-900">3. Non-partage des données</h2>
          <p>
            Vos informations personnelles ne sont en aucun cas revendues ni cédées à des tiers à des fins publicitaires. Elles ne sont partagées qu'avec notre prestataire technique de paiement (pawaPay) pour la finalisation de vos transactions.
          </p>
        </section>
      </div>
    </div>
  </SiteLayout>
</template>
