<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { SITE_NAME } from '@/Utils/site';
</script>

<template>
  <SiteLayout>
    <Head title="Mentions Légales" />

    <div class="container-page max-w-3xl py-12 space-y-8">
      <div>
        <h1 class="h1">Mentions Légales</h1>
        <p class="text-xs text-slate-400 mt-2">Dernière mise à jour : 12 novembre 2026</p>
      </div>

      <div class="card p-6 sm:p-8 space-y-6 shadow-card text-xs sm:text-sm text-slate-600 leading-relaxed">
        <section class="space-y-2">
          <h2 class="h3 text-base text-slate-900">Éditeur du service</h2>
          <p>
            Le site <strong>{{ SITE_NAME }}</strong> est opéré par un service indépendant spécialisé dans la distribution de licences et d'abonnements logiciels à destination de l'Afrique de l'Ouest et de l'Afrique Centrale.
          </p>
        </section>

        <section class="space-y-2">
          <h2 class="h3 text-base text-slate-900">Propriété intellectuelle</h2>
          <p>
            Les marques, logos et dénominations commerciales cités sur ce site (ex. Netflix, Canva, ChatGPT, Prime Video) demeurent la propriété exclusive de leurs détenteurs respectifs. Leur mention n'intervient qu'aux strictes fins d'identification des licences et compatibilités proposées.
          </p>
        </section>
      </div>
    </div>
  </SiteLayout>
</template>
