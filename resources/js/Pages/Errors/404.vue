<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ShoppingBag, ArrowLeft } from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';
</script>

<template>
  <SiteLayout>
    <Head title="Page non trouvée — Erreur 404" />

    <div class="container-page py-20 max-w-md text-center space-y-6">
      <div class="size-20 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
        <ShoppingBag class="size-10" />
      </div>

      <div class="space-y-2">
        <h1 class="h1 text-3xl">Page introuvable</h1>
        <p class="text-sm text-slate-500 leading-relaxed">
          L'adresse demandée n'existe pas ou le produit a été déplacé.
        </p>
      </div>

      <div class="pt-2">
        <Link href="/" class="btn btn-primary min-h-11">
          <ArrowLeft class="size-4" />
          Retour à l'accueil
        </Link>
      </div>
    </div>
  </SiteLayout>
</template>
