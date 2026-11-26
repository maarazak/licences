<script setup lang="ts">
import type { Component } from 'vue';
import { Check } from 'lucide-vue-next';

interface PayLogo {
  src: string;
  label: string;
}

/**
 * Carte de choix d'un moyen de paiement (bouton radio) : icône, titre, sous-titre, logos des moyens acceptés.
 * Le contenu à droite (ex. solde disponible) se place dans le slot `aside`.
 */
const props = defineProps<{
  selected: boolean;
  disabled?: boolean;
  title: string;
  subtitle?: string;
  /** Icône générique (ex. portefeuille), utilisée si `iconLogos` est absent. */
  icon?: Component;
  /** Vrais logos empilés à la place de l'icône (2 ou 3 logos). */
  iconLogos?: PayLogo[];
  logos?: PayLogo[];
}>();

const emit = defineEmits<{ (e: 'select'): void }>();

function choose() {
  if (!props.disabled) emit('select');
}
</script>

<template>
  <div
    role="radio"
    :aria-checked="selected"
    :aria-disabled="disabled || undefined"
    :tabindex="disabled ? -1 : 0"
    @click="choose"
    @keydown.enter.prevent="choose"
    @keydown.space.prevent="choose"
    :class="[
      'group relative flex items-start gap-3.5 rounded-2xl border p-4 transition duration-200 outline-none focus-visible:ring-2 focus-visible:ring-brand/40',
      disabled
        ? 'cursor-not-allowed border-line bg-slate-50/70 opacity-60'
        : selected
          ? 'cursor-pointer border-brand bg-brand/[0.035] shadow-[0_8px_24px_-14px_rgb(13_92_74/0.55)] ring-1 ring-brand/25'
          : 'cursor-pointer border-line bg-white hover:border-slate-300 hover:shadow-sm',
    ]"
  >
    <span v-if="iconLogos?.length" class="flex h-11 shrink-0 items-center pl-1" aria-hidden="true">
      <img
        v-for="(logo, i) in iconLogos"
        :key="logo.src"
        :src="logo.src"
        alt=""
        :class="[
          'size-10 rounded-xl bg-white object-contain p-1.5 shadow-sm ring-2 transition',
          i > 0 ? '-ml-4' : '',
          selected && !disabled ? 'ring-brand/30' : 'ring-white',
        ]"
      />
    </span>
    <span
      v-else
      :class="[
        'flex size-11 shrink-0 items-center justify-center rounded-xl transition',
        selected && !disabled ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600 group-hover:bg-slate-200/70',
      ]"
    >
      <component :is="icon" class="size-5" aria-hidden="true" />
    </span>

    <div class="min-w-0 flex-1">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="text-sm font-bold leading-tight text-ink">{{ title }}</p>
          <p v-if="subtitle" class="mt-1 text-xs leading-snug text-muted">{{ subtitle }}</p>
        </div>
        <slot name="aside" />
      </div>

      <ul v-if="logos?.length" class="mt-3 flex flex-wrap items-center gap-1.5">
        <li
          v-for="logo in logos"
          :key="logo.src"
          class="flex h-7 items-center justify-center rounded-lg bg-white px-1.5 ring-1 ring-black/[0.07]"
        >
          <img :src="logo.src" :alt="logo.label" :title="logo.label" loading="lazy" class="h-4 w-auto max-w-[44px] object-contain" />
        </li>
      </ul>
    </div>

    <span
      :class="[
        'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border-2 transition',
        selected && !disabled ? 'border-brand bg-brand text-white' : 'border-slate-300 bg-white',
      ]"
      aria-hidden="true"
    >
      <Check v-if="selected && !disabled" class="size-3" :stroke-width="3.5" />
    </span>
  </div>
</template>
