<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Play, Pause } from 'lucide-vue-next';
import type { LogoSpec } from '@/Types/logo';
import BrandLogo from '@/Components/Shared/BrandLogo.vue';
import BrandTint from '@/Components/Shared/BrandTint.vue';

export interface HeroSlide {
  slug: string;
  title: string;
  duration: string;
  price: string;
  shelf: string;
  badge: string;
  badgeTone: 'success' | 'warning' | 'danger';
  logo: LogoSpec;
}

const props = withDefaults(
  defineProps<{
    slides: HeroSlide[];
    cta?: string;
  }>(),
  {
    cta: "Voir l'offre",
  }
);

const index = ref(0);
const paused = ref(false);
const reducedMotion = ref(false);

const count = computed(() => props.slides.length);

const position = (offset: number, total: number): string => {
  if (offset === 0) return 'z-30 scale-100 rotate-0 opacity-100';
  if (offset === 1) return 'z-20 translate-x-[58%] scale-[0.88] rotate-[5deg] opacity-70 max-sm:pointer-events-none max-sm:opacity-0';
  if (offset === total - 1) return 'z-20 -translate-x-[58%] scale-[0.88] -rotate-[5deg] opacity-70 max-sm:pointer-events-none max-sm:opacity-0';
  return 'pointer-events-none z-10 scale-75 opacity-0';
};

const roundBtn =
  'flex size-10 items-center justify-center rounded-full border border-line bg-white text-ink shadow-sm transition hover:-translate-y-0.5 hover:shadow-card cursor-pointer';

let timer: ReturnType<typeof setInterval> | null = null;

const startTimer = () => {
  stopTimer();
  if (!paused.value && !reducedMotion.value && count.value > 1) {
    timer = setInterval(() => {
      index.value = (index.value + 1) % count.value;
    }, 4500);
  }
};

const stopTimer = () => {
  if (timer) {
    clearInterval(timer);
    timer = null;
  }
};

onMounted(() => {
  const query = window.matchMedia('(prefers-reduced-motion: reduce)');
  reducedMotion.value = query.matches;
  startTimer();
});

onUnmounted(() => {
  stopTimer();
});

watch([paused, reducedMotion, count], () => {
  startTimer();
});

const prev = () => {
  index.value = (index.value - 1 + count.value) % count.value;
  startTimer();
};

const next = () => {
  index.value = (index.value + 1) % count.value;
  startTimer();
};

const togglePause = () => {
  paused.value = !paused.value;
};

const floating = computed(() => [props.slides[3], props.slides[4], props.slides[5]].filter(Boolean));
</script>

<template>
  <div v-if="count > 0" role="group" aria-roledescription="carrousel" aria-label="Offres à la une" class="relative">
    <div aria-hidden="true" class="absolute -inset-6 -z-10 rounded-full bg-brand-bright/15 blur-3xl" />

    <!-- Logos flottants décoratifs -->
    <BrandLogo
      v-if="floating[0]"
      :logo="floating[0].logo"
      size="md"
      class="animate-float absolute -top-3 left-0 z-40 hidden sm:flex"
    />
    <BrandLogo
      v-if="floating[1]"
      :logo="floating[1].logo"
      size="md"
      class="animate-float-slow absolute right-0 bottom-24 z-40 hidden sm:flex"
    />
    <BrandLogo
      v-if="floating[2]"
      :logo="floating[2].logo"
      size="sm"
      class="animate-float absolute top-1 right-8 z-40 hidden sm:flex"
    />

    <!-- Conteneur 3D du jeu de cartes glissantes -->
    <div class="relative mx-auto h-[25rem] w-full max-w-[34rem]">
      <Link
        v-for="(slide, i) in slides"
        :key="slide.slug"
        :href="`/boutique/${slide.slug}`"
        :tabindex="((i - index + count) % count) === 0 ? 0 : -1"
        :class="[
          'absolute inset-x-0 top-2 mx-auto block w-[17rem] overflow-hidden rounded-3xl bg-white text-ink shadow-[0_30px_60px_-24px_rgb(11_18_32/0.4)] ring-1 ring-line transition duration-700 ease-out sm:w-72',
          position((i - index + count) % count, count),
        ]"
      >
        <div class="relative h-32">
          <BrandTint :logo="slide.logo" :strength="0.2" />
          <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-b from-white/0 to-white/80" />
          <span
            v-if="slide.shelf"
            class="absolute top-4 left-4 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-bold text-ink/70 shadow-sm"
          >
            {{ slide.shelf }}
          </span>
          <BrandLogo :logo="slide.logo" size="xl" className="absolute -bottom-10 left-5" />
        </div>

        <div class="px-5 pt-12 pb-5">
          <p translate="no" class="font-display text-lg leading-snug font-extrabold text-ink line-clamp-1">
            {{ slide.title }}
          </p>
          <p class="mt-1 text-xs font-medium text-muted">
            {{ slide.duration }}
          </p>
          <div class="mt-3">
            <span
              :class="[
                'badge',
                slide.badgeTone === 'danger'
                  ? 'badge-danger'
                  : slide.badgeTone === 'warning'
                    ? 'badge-warning'
                    : 'badge-success',
              ]"
            >
              {{ slide.badge }}
            </span>
          </div>
          <div class="mt-4 flex items-center justify-between gap-3 border-t border-line pt-4">
            <p class="font-display text-xl font-extrabold text-ink">
              {{ slide.price }}
            </p>
            <span class="btn btn-primary btn-sm pointer-events-none whitespace-nowrap">
              {{ cta }}
              <ArrowRight class="size-4" aria-hidden="true" />
            </span>
          </div>
        </div>
      </Link>
    </div>

    <!-- Contrôles et barre de progression -->
    <div v-if="count > 1" class="mx-auto mt-2 max-w-[34rem] space-y-4">
      <div class="h-1 overflow-hidden rounded-full bg-ink/10" aria-hidden="true">
        <div
          :key="index"
          :class="[
            'animate-progress h-full rounded-full bg-gradient-to-r from-brand-bright to-accent',
            paused ? '[animation-play-state:paused]' : '',
          ]"
        />
      </div>

      <div class="flex items-center justify-between gap-3">
        <!-- Pagination à points -->
        <div class="flex items-center gap-1">
          <button
            v-for="(slide, i) in slides"
            :key="slide.slug"
            type="button"
            :aria-label="`Offre ${i + 1} sur ${count} : ${slide.title}`"
            :aria-current="i === index"
            class="flex size-6 items-center justify-center cursor-pointer"
            @click="index = i"
          >
            <span
              :class="[
                'block rounded-full transition-all duration-300',
                i === index ? 'h-2 w-6 bg-ink' : 'size-2 bg-ink/20 hover:bg-ink/40',
              ]"
            />
          </button>
        </div>

        <!-- Flèches et pause -->
        <div class="flex items-center gap-2">
          <button type="button" aria-label="Offre précédente" :class="roundBtn" @click="prev">
            <ArrowLeft class="size-4" aria-hidden="true" />
          </button>
          <button
            type="button"
            :aria-label="paused ? 'Lecture' : 'Pause'"
            :aria-pressed="paused"
            :class="roundBtn"
            @click="togglePause"
          >
            <Play v-if="paused || reducedMotion" class="size-4" aria-hidden="true" />
            <Pause v-else class="size-4" aria-hidden="true" />
          </button>
          <button type="button" aria-label="Offre suivante" :class="roundBtn" @click="next">
            <ArrowRight class="size-4" aria-hidden="true" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
