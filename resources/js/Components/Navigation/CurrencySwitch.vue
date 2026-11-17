<script setup lang="ts">
import { ref } from 'vue';

const currentCurrency = ref(localStorage.getItem('user_currency') || 'XOF');

const setCurrency = (c: string) => {
  currentCurrency.value = c;
  localStorage.setItem('user_currency', c);
  window.dispatchEvent(new CustomEvent('currency-changed', { detail: c }));
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
