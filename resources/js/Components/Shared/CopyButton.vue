<script setup lang="ts">
import { ref } from 'vue';
import { Copy, Check } from 'lucide-vue-next';

const props = defineProps<{
  value: string;
}>();

const copied = ref(false);

const handleCopy = async () => {
  try {
    await navigator.clipboard.writeText(props.value);
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  } catch {
    copied.value = false;
  }
};
</script>

<template>
  <button
    type="button"
    @click="handleCopy"
    class="btn btn-soft btn-sm shrink-0 inline-flex items-center gap-1.5"
    :aria-label="copied ? 'Copié' : 'Copier'"
  >
    <Check v-if="copied" class="size-3.5 text-emerald-600" />
    <Copy v-else class="size-3.5 text-slate-500" />
    <span :class="copied ? 'text-emerald-700 font-semibold' : ''">
      {{ copied ? 'Copié' : 'Copier' }}
    </span>
  </button>
</template>
