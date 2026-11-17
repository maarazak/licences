<script setup lang="ts">
import { computed } from 'vue';
import { resolveBrand } from '@/Utils/brands';

const props = withDefaults(
  defineProps<{
    name: string;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    image?: string;
    className?: string;
  }>(),
  {
    size: 'md',
    className: '',
  }
);

const brand = computed(() => {
  const b = resolveBrand(props.name);
  if (props.image) {
    b.image = props.image;
  }
  return b;
});

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'size-10 rounded-xl text-xs';
    case 'lg':
      return 'size-16 rounded-2xl text-xl';
    case 'xl':
      return 'size-20 rounded-3xl text-2xl';
    case 'md':
    default:
      return 'size-14 rounded-2xl text-base';
  }
});
</script>

<template>
  <div
    :class="[
      sizeClasses,
      'flex shrink-0 items-center justify-center overflow-hidden shadow-sm ring-1 ring-black/5 bg-white relative font-display font-extrabold select-none',
      className,
    ]"
    :title="name"
  >
    <img
      v-if="brand.image"
      :src="brand.image"
      :alt="brand.label"
      loading="lazy"
      class="size-full object-contain p-2"
    />
    <div
      v-else
      class="size-full flex items-center justify-center text-white"
      :style="{ backgroundColor: brand.hex }"
    >
      <span>{{ brand.mark }}</span>
    </div>
  </div>
</template>
