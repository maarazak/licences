<script setup lang="ts">
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();
const currentCurrency = computed(() => (page.props.locale as any)?.currency || 'XOF');

const setCurrency = (currency: string) => {
  if (currency === currentCurrency.value) return;
  router.post('/locale/currency', { currency }, {
    preserveScroll: true,
    onSuccess: () => {
      window.location.reload();
    },
  });
};
</script>

<template>
  <div role="group" aria-label="Devise" class="flex rounded-full border border-line bg-white p-0.5 shadow-sm">
    <button
      type="button"
      :aria-pressed="currentCurrency === 'XOF'"
      :class="[
        'min-h-8 cursor-pointer rounded-full px-3 text-xs font-bold transition',
        currentCurrency === 'XOF' ? 'bg-ink text-white' : 'text-ink/70 hover:text-ink',
      ]"
      @click="setCurrency('XOF')"
    >
      FCFA
    </button>
    <button
      type="button"
      :aria-pressed="currentCurrency === 'USD'"
      :class="[
        'min-h-8 cursor-pointer rounded-full px-3 text-xs font-bold transition',
        currentCurrency === 'USD' ? 'bg-ink text-white' : 'text-ink/70 hover:text-ink',
      ]"
      @click="setCurrency('USD')"
    >
      USD
    </button>
  </div>
</template>
