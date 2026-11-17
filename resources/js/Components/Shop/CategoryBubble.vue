<script setup lang="ts">
import { computed } from 'vue';
import {
  Sparkles,
  Palette,
  CodeXml,
  Briefcase,
  BookOpen,
  Play,
  ShieldCheck,
  UserRound,
  Tag,
} from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    name: string;
    size?: 'sm' | 'md';
  }>(),
  {
    size: 'md',
  }
);

const STYLES: Record<string, { gradient: string; icon: any }> = {
  'IA et assistants': { gradient: 'from-violet-500 to-fuchsia-500', icon: Sparkles },
  'Création et design': { gradient: 'from-pink-500 to-orange-400', icon: Palette },
  'Développement': { gradient: 'from-sky-500 to-indigo-500', icon: CodeXml },
  'Bureautique': { gradient: 'from-emerald-500 to-teal-500', icon: Briefcase },
  'Apprentissage': { gradient: 'from-amber-400 to-orange-500', icon: BookOpen },
  'Divertissement': { gradient: 'from-rose-500 to-red-500', icon: Play },
  'VPN et sécurité': { gradient: 'from-slate-600 to-slate-800', icon: ShieldCheck },
  'Comptes et réseaux': { gradient: 'from-cyan-500 to-blue-500', icon: UserRound },
  'Systèmes': { gradient: 'from-blue-600 to-cyan-500', icon: Sparkles },
  'Sécurité': { gradient: 'from-slate-600 to-slate-800', icon: ShieldCheck },
  'Design': { gradient: 'from-pink-500 to-orange-400', icon: Palette },
};

const resolved = computed(() => {
  return STYLES[props.name] || { gradient: 'from-brand to-brand-bright', icon: Tag };
});
</script>

<template>
  <span
    :class="[
      'flex shrink-0 items-center justify-center bg-gradient-to-br text-white shadow-[0_10px_20px_-10px_rgb(11_18_32/0.5)]',
      resolved.gradient,
      size === 'sm' ? 'size-8 rounded-lg' : 'size-12 rounded-2xl',
    ]"
  >
    <component :is="resolved.icon" :class="size === 'sm' ? 'size-4' : 'size-6'" aria-hidden="true" />
  </span>
</template>
