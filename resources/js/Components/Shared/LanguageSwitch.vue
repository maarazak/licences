<script setup lang="ts">
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { Globe } from 'lucide-vue-next';

const page = usePage();
const currentLang = computed(() => (page.props.locale as any)?.lang || 'fr');

const setLang = (lang: string) => {
  router.post('/locale/lang', { lang }, {
    preserveScroll: true,
  });
};
</script>

<template>
  <div class="relative inline-flex items-center">
    <Globe class="pointer-events-none absolute left-3 size-3.5 text-slate-400" />
    <select
      :value="currentLang"
      @change="(e: any) => setLang(e.target.value)"
      aria-label="Langue"
      class="min-h-9 cursor-pointer appearance-none rounded-full border border-line bg-white pr-3 pl-8 text-xs font-bold text-ink shadow-sm transition hover:border-ink/40 focus:outline-none"
    >
      <option value="fr">Français</option>
      <option value="en">English</option>
    </select>
  </div>
</template>
