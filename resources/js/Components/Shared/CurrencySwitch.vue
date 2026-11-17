<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const currentCurrency = ref(document.cookie.match(/currency=([^;]+)/)?.[1] || 'XOF');

const setCurrency = (c: string) => {
  currentCurrency.value = c;
  document.cookie = `currency=${c};path=/;max-age=31536000;SameSite=Lax`;
  router.reload();
};
</script>

<template>
  <div class="inline-flex rounded-full border border-line bg-canvas p-0.5 shadow-sm">
    <button
      type="button"
      @click="setCurrency('XOF')"
      :class="[
        'rounded-full px-3 py-1 text-xs font-bold transition',
        currentCurrency === 'XOF' ? 'bg-brand text-white shadow-sm' : 'text-muted hover:text-ink'
      ]"
    >
      FCFA
    </button>
    <button
      type="button"
      @click="setCurrency('USD')"
      :class="[
        'rounded-full px-3 py-1 text-xs font-bold transition',
        currentCurrency === 'USD' ? 'bg-brand text-white shadow-sm' : 'text-muted hover:text-ink'
      ]"
    >
      USD ($)
    </button>
  </div>
</template>
