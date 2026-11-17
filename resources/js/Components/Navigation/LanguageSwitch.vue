<script setup lang="ts">
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { Globe } from 'lucide-vue-next';

const page = usePage();
const currentLang = computed(() => (page.props.locale as any)?.lang || 'fr');

const changeLang = (event: Event) => {
  const target = event.target as HTMLSelectElement;
  const lang = target.value;
  router.post('/locale/lang', { lang }, {
    preserveScroll: true,
    onSuccess: () => {
      window.location.reload();
    },
  });
};
</script>

<template>
  <div class="relative">
    <Globe class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
    <select
      :value="currentLang"
      aria-label="Langue"
      class="min-h-9 cursor-pointer appearance-none rounded-full border border-line bg-white pr-3 pl-9 text-xs font-bold text-ink shadow-sm transition hover:border-ink/40"
      @change="changeLang"
    >
      <option value="fr">Français</option>
      <option value="en">English</option>
    </select>
  </div>
</template>
