<script setup lang="ts">
export type PayMethod = 'orange' | 'moov' | 'wave' | 'usdt';

const METHODS: Record<PayMethod, { src: string; label: string; pad: string }> = {
  orange: { src: '/payments/orange-money.png', label: 'Orange Money', pad: 'p-[3%]' },
  moov: { src: '/payments/moov-money.png', label: 'Moov Money', pad: 'p-[3%]' },
  wave: { src: '/payments/wave.png', label: 'Wave', pad: 'p-[6%]' },
  usdt: { src: '/payments/usdt.svg', label: 'USDT', pad: 'p-[14%]' },
};

withDefaults(
  defineProps<{
    layout?: 'floating' | 'row';
    methods?: PayMethod[];
  }>(),
  {
    layout: 'floating',
    methods: () => ['orange', 'moov', 'wave', 'usdt'],
  }
);
</script>

<template>
  <div v-if="layout === 'floating'" class="flex items-center gap-2" role="group">
    <span
      v-for="(method, i) in methods"
      :key="method"
      class="animate-float-sm size-9 sm:size-10 flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white shadow-md ring-1 ring-black/5"
      :style="{ animationDelay: `${-i * 1.5}s` }"
      :title="METHODS[method].label"
    >
      <img
        :src="METHODS[method].src"
        :alt="METHODS[method].label"
        class="size-full object-contain"
        :class="METHODS[method].pad"
      />
    </span>
  </div>

  <ul v-else class="flex flex-wrap items-center gap-x-4 gap-y-3">
    <li v-for="method in methods" :key="method" class="flex items-center gap-2">
      <span class="size-8 flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-black/5">
        <img
          :src="METHODS[method].src"
          :alt="METHODS[method].label"
          class="size-full object-contain"
          :class="METHODS[method].pad"
        />
      </span>
      <span class="text-xs font-semibold text-slate-700">{{ METHODS[method].label }}</span>
    </li>
  </ul>
</template>
