<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';

defineProps<{
  title: string;
  updated: string;
  updatedLabel?: string;
  sections: Array<{
    title: string;
    paragraphs: string[];
  }>;
}>();
</script>

<template>
  <SiteLayout>
    <Head :title="title" />

    <div class="container-page max-w-3xl py-10">
      <h1 class="h1">{{ title }}</h1>
      <p class="mt-2 text-xs text-muted">
        {{ updatedLabel || 'Dernière mise à jour' }} : {{ updated }}
      </p>
      <div class="mt-8 space-y-8">
        <section v-for="section in sections" :key="section.title">
          <h2 class="h3 text-lg">{{ section.title }}</h2>
          <p
            v-for="p in section.paragraphs"
            :key="p"
            class="mt-2 text-sm leading-relaxed text-ink/90"
          >
            {{ p }}
          </p>
        </section>
      </div>
    </div>
  </SiteLayout>
</template>
