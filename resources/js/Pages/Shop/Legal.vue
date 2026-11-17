<script setup lang="ts">
import LegalPage from '@/Components/Shared/LegalPage.vue';
import { SITE_NAME } from '@/Utils/site';

const sections = [
  {
    title: 'Indépendance',
    paragraphs: [
      `${SITE_NAME} est une boutique indépendante. Les noms et logos des services proposés appartiennent à leurs propriétaires respectifs et ne sont utilisés que pour désigner le service vendu, sans partenariat officiel.`
    ]
  }
];
</script>

<template>
  <LegalPage
    title="Mentions légales"
    updated="12 novembre 2026"
    updated-label="Dernière mise à jour"
    :sections="sections"
  />
</template>
