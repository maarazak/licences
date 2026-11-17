<script setup lang="ts">
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();
const currentCurrency = computed(() => (page.props.locale as any)?.currency || 'XOF');

const setCurrency = (currency: string) => {
  if (currency === currentCurrency.value) return;
  router.post('/locale/currency', { currency }, {
    preserveScroll: true,
  });
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
