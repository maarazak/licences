<script setup lang="ts">
withDefaults(
  defineProps<{
    layout?: 'floating' | 'row';
    methods?: Array<'orange' | 'moov' | 'wave' | 'usdt'>;
  }>(),
  {
    layout: 'floating',
    methods: () => ['orange', 'moov', 'wave', 'usdt'],
  }
);

const METHODS: Record<string, { src: string; label: string; pad: string }> = {
  orange: { src: '/payments/orange-money.png', label: 'Orange Money', pad: 'p-[3%]' },
  moov: { src: '/payments/moov-money.png', label: 'Moov Money', pad: 'p-[3%]' },
  wave: { src: '/payments/wave.png', label: 'Wave', pad: 'p-[6%]' },
  usdt: { src: '/payments/usdt.svg', label: 'USDT', pad: 'p-[14%]' },
};
</script>

<template>
  <!-- Miniatures flottantes (Héros accueil) -->
  <div
    v-if="layout === 'floating'"
    class="flex items-center gap-2"
    aria-label="Orange Money, Moov Money, Wave, USDT"
    role="group"
  >
    <span
      class="animate-float-sm size-9 sm:size-10 flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white shadow-logo ring-1 ring-black/5"
      role="img"
      :aria-label="METHODS.orange.label"
      :title="METHODS.orange.label"
    >
      <img :src="METHODS.orange.src" alt="" width="96" height="96" loading="lazy" class="size-full object-contain p-[3%]" />
    </span>
    <span
      class="animate-float-sm-slow size-9 sm:size-10 flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white shadow-logo ring-1 ring-black/5"
      role="img"
      :aria-label="METHODS.moov.label"
      :title="METHODS.moov.label"
    >
      <img :src="METHODS.moov.src" alt="" width="96" height="96" loading="lazy" class="size-full object-contain p-[3%]" />
    </span>
    <span
      class="animate-float-sm size-9 sm:size-10 [animation-delay:-3s] flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white shadow-logo ring-1 ring-black/5"
      role="img"
      :aria-label="METHODS.wave.label"
      :title="METHODS.wave.label"
    >
      <img :src="METHODS.wave.src" alt="" width="96" height="96" loading="lazy" class="size-full object-contain p-[6%]" />
    </span>
    <span
      class="animate-float-sm-slow size-9 sm:size-10 [animation-delay:-1.5s] flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white shadow-logo ring-1 ring-black/5"
      role="img"
      :aria-label="METHODS.usdt.label"
      :title="METHODS.usdt.label"
    >
      <img :src="METHODS.usdt.src" alt="" width="96" height="96" loading="lazy" class="size-full object-contain p-[14%]" />
    </span>
  </div>

  <!-- Ligne complète (Pied de page) -->
  <ul v-else class="flex flex-wrap items-center gap-x-4 gap-y-3">
    <li v-for="method in methods" :key="method" class="flex items-center gap-2">
      <span
        class="size-8 flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white shadow-logo ring-1 ring-black/5"
        role="img"
        :aria-label="METHODS[method].label"
        :title="METHODS[method].label"
      >
        <img
          :src="METHODS[method].src"
          :alt="METHODS[method].label"
          width="96"
          height="96"
          loading="lazy"
          :class="['size-full object-contain', METHODS[method].pad]"
        />
      </span>
      <span class="text-xs font-semibold text-ink/75">{{ METHODS[method].label }}</span>
    </li>
  </ul>
</template>
