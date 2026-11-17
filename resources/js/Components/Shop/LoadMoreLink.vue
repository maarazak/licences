<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';

defineProps<{
  href: string;
  label?: string;
  hint?: string;
}>();
</script>

<template>
  <div class="mt-8 flex justify-center">
    <Link
      :href="href"
      :preserve-scroll="true"
      class="btn btn-soft min-h-12 w-full max-w-sm text-sm inline-flex items-center justify-center gap-2"
    >
      <ChevronDown class="size-4" aria-hidden="true" />
      <span>{{ label || 'Charger plus' }}</span>
      <span v-if="hint" class="text-xs font-medium text-muted">· {{ hint }}</span>
    </Link>
  </div>
</template>
