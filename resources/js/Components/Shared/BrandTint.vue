<script setup lang="ts">
import type { LogoSpec } from '@/Types/logo';

withDefaults(
  defineProps<{
    logo: LogoSpec;
    strength?: number;
  }>(),
  {
    strength: 0.16,
  }
);
</script>

<template>
  <svg class="absolute inset-0 size-full" preserveAspectRatio="none" aria-hidden="true">
    <rect width="100%" height="100%" :fill="logo?.tint || '#0d5c4a'" :fill-opacity="strength" />
  </svg>
</template>
