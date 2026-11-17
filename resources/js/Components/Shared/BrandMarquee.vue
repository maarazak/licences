<script setup lang="ts">
import type { LogoSpec } from '@/Types/logo';
import BrandLogo from '@/Components/Shared/BrandLogo.vue';

withDefaults(
  defineProps<{
    logos: LogoSpec[];
    label?: string;
  }>(),
  {
    label: 'Marques proposées',
  }
);
</script>

<template>
  <div v-if="logos && logos.length >= 4" class="marquee-pause fade-x overflow-hidden py-6" :aria-label="label">
    <ul class="animate-marquee flex w-max items-center gap-12 pr-12">
      <!-- 2 copies for infinite seamless scroll -->
      <template v-for="copy in [0, 1]" :key="copy">
        <li
          v-for="logo in logos"
          :key="`${copy}-${logo.label}`"
          :aria-hidden="copy === 1"
          class="flex shrink-0 items-center gap-3"
        >
          <BrandLogo :logo="logo" size="sm" />
          <span translate="no" class="font-display text-sm font-bold text-ink/80">
            {{ logo.label }}
          </span>
        </li>
      </template>
    </ul>
  </div>
</template>
