<script setup lang="ts">
import { ref } from 'vue';
import { Globe } from 'lucide-vue-next';

const currentLang = ref(localStorage.getItem('user_lang') || 'fr');

const changeLang = (event: Event) => {
  const target = event.target as HTMLSelectElement;
  currentLang.value = target.value;
  localStorage.setItem('user_lang', target.value);
};
</script>

<template>
  <div class="relative">
    <Globe class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
    <select
      v-model="currentLang"
      aria-label="Langue"
      class="min-h-9 cursor-pointer appearance-none rounded-full border border-line bg-white pr-3 pl-9 text-xs font-bold text-ink shadow-sm transition hover:border-ink/40"
      @change="changeLang"
    >
      <option value="fr">Français</option>
      <option value="en">English</option>
    </select>
  </div>
</template>
