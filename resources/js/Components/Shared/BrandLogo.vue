<script setup lang="ts">
import { computed } from 'vue';
import type { LogoSpec } from '@/Types/logo';
import { logoFor } from '@/Utils/brands';

const props = withDefaults(
  defineProps<{
    logo?: LogoSpec;
    name?: string;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    className?: string;
  }>(),
  {
    size: 'md',
    className: '',
  }
);

const resolvedLogo = computed<LogoSpec>(() => {
  if (props.logo) return props.logo;
  return logoFor(props.name || 'Licences');
});

const SIZES = {
  sm: 'size-10 rounded-xl',
  md: 'size-14 rounded-2xl',
  lg: 'size-[72px] rounded-2xl',
  xl: 'size-24 rounded-3xl',
} as const;
</script>

<template>
  <!-- Monogramme SVG -->
  <span
    v-if="resolvedLogo.kind === 'mono'"
    :class="[SIZES[size], 'flex shrink-0 items-center justify-center overflow-hidden shadow-logo ring-1 ring-black/5', className]"
    role="img"
    :aria-label="resolvedLogo.label"
  >
    <svg viewBox="0 0 64 64" class="size-full" aria-hidden="true">
      <rect width="64" height="64" :fill="resolvedLogo.hex" />
      <circle cx="54" cy="8" r="34" fill="#fff" fill-opacity="0.14" />
      <rect y="38" width="64" height="26" fill="#000" fill-opacity="0.12" />
      <text
        x="32"
        y="43"
        text-anchor="middle"
        :font-size="resolvedLogo.letters.length > 1 ? 24 : 30"
        font-weight="800"
        fill="#fff"
        class="font-display select-none"
      >
        {{ resolvedLogo.letters }}
      </text>
    </svg>
  </span>

  <!-- Image PNG/JPG -->
  <span
    v-else-if="resolvedLogo.kind === 'image'"
    :class="[SIZES[size], 'bg-white flex shrink-0 items-center justify-center overflow-hidden shadow-logo ring-1 ring-black/5', className]"
    role="img"
    :aria-label="resolvedLogo.label"
  >
    <img :src="resolvedLogo.src" alt="" loading="lazy" class="size-full object-contain p-[18%]" />
  </span>

  <!-- Pictogramme Simple-Icons -->
  <span
    v-else
    :class="[SIZES[size], 'bg-white flex shrink-0 items-center justify-center overflow-hidden shadow-logo ring-1 ring-black/5', className]"
    role="img"
    :aria-label="resolvedLogo.label"
  >
    <svg viewBox="0 0 24 24" class="size-[52%]" aria-hidden="true">
      <path :d="resolvedLogo.path" :fill="resolvedLogo.hex" />
    </svg>
  </span>
</template>
