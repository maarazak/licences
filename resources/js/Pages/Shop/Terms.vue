<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { SITE_NAME } from '@/Utils/site';

const sections = [
  {
    title: '1. Objet',
    paragraphs: [
      `${SITE_NAME} vend des accès à des services numériques édités par des tiers : abonnements, licences, codes d’activation ou comptes préconfigurés. ${SITE_NAME} est un distributeur indépendant, sans affiliation directe avec les éditeurs desdits services.`,
    ],
  },
  {
    title: '2. Compte client',
    paragraphs: [
      'L’achat nécessite un compte client, généré automatiquement lors de votre première authentification par code e-mail. Vous êtes responsable du maintien de la sécurité de votre boîte de messagerie.',
    ],
  },
  {
    title: '3. Prix et paiement',
    paragraphs: [
      'Tous les prix sont affichés en Francs CFA (XOF / XAF à parité 1:1) et sont fermes et définitifs. Le paiement s’opère sur la page sécurisée de notre prestataire pawaPay ou directement par prélèvement sur votre solde client.',
      'La commande n’est considérée comme conclue qu’après notification de succès émise par la passerelle de paiement.',
    ],
  },
  {
    title: '4. Modalités de livraison',
    paragraphs: [
      'Les accès numériques sont mis à disposition directement dans votre espace client (rubrique Mes achats) dès réception de la confirmation de paiement, en général en quelques minutes.',
      'Les accès fournis sont strictement personnels et doivent être utilisés dans le respect des règles d’usage de chaque service.',
    ],
  },
  {
    title: '5. Garantie et remplacement',
    paragraphs: [
      'La durée de garantie est spécifiée sur chaque fiche produit. Tout accès défaillant signalé via notre système d’assistance durant cette période fait l’objet d’un remplacement immédiat ou d’un remboursement sur votre solde.',
    ],
  },
];
</script>

<template>
  <SiteLayout>
    <Head title="Conditions Générales de Vente" />

    <div class="container-page max-w-3xl py-12 space-y-8">
      <div>
        <h1 class="h1">Conditions Générales de Vente</h1>
        <p class="text-xs text-slate-400 mt-2">Dernière mise à jour : 12 novembre 2026</p>
      </div>

      <div class="card p-6 sm:p-8 space-y-6 shadow-card">
        <section v-for="sec in sections" :key="sec.title" class="space-y-2">
          <h2 class="h3 text-base text-slate-900">{{ sec.title }}</h2>
          <p
            v-for="(p, i) in sec.paragraphs"
            :key="i"
            class="text-xs sm:text-sm text-slate-600 leading-relaxed"
          >
            {{ p }}
          </p>
        </section>
      </div>
    </div>
  </SiteLayout>
</template>
