<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { Search, ChevronDown, X } from 'lucide-vue-next';

export interface SearchItem {
  slug: string;
  title: string;
  shelf: string;
}

const props = withDefaults(
  defineProps<{
    items?: SearchItem[];
    mode?: 'desktop' | 'mobile';
  }>(),
  {
    items: () => [],
    mode: 'desktop',
  }
);

const emit = defineEmits(['close']);

const query = ref('');
const open = ref(false);
const active = ref(-1);
const mobileOpen = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);

const norm = (v: string) =>
  v
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
    .toLowerCase();

const matches = computed(() => {
  const q = norm(query.value.trim());
  if (!q) return props.items.slice(0, 50);
  return props.items.filter((item) => {
    const key = norm(`${item.title} ${item.shelf}`);
    return key.includes(q);
  });
});

const pick = (slug: string) => {
  open.value = false;
  mobileOpen.value = false;
  query.value = '';
  active.value = -1;
  router.visit(`/boutique/${encodeURIComponent(slug)}`);
};

const submitSearch = () => {
  open.value = false;
  mobileOpen.value = false;
  const q = query.value.trim();
  router.visit(q ? `/boutique?q=${encodeURIComponent(q.slice(0, 80))}` : '/boutique');
};

const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    open.value = true;
    active.value = Math.min(active.value + 1, matches.value.length - 1);
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    active.value = Math.max(active.value - 1, -1);
  } else if (event.key === 'Escape') {
    open.value = false;
    mobileOpen.value = false;
  } else if (event.key === 'Enter' && open.value && active.value >= 0 && matches.value[active.value]) {
    event.preventDefault();
    pick(matches.value[active.value].slug);
  }
};

const openMobile = () => {
  mobileOpen.value = true;
  nextTick(() => {
    inputRef.value?.focus();
  });
};
</script>

<template>
  <!-- DESKTOP SEARCH -->
  <form
    v-if="mode === 'desktop'"
    role="search"
    class="relative w-full"
    @submit.prevent="submitSearch"
  >
    <Search class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
    <input
      type="search"
      role="combobox"
      aria-label="Rechercher un produit"
      :aria-expanded="open"
      autocomplete="off"
      maxlength="80"
      placeholder="Rechercher un produit…"
      v-model="query"
      class="input min-h-10 rounded-full border-line bg-canvas pr-11 pl-10 text-sm shadow-none"
      @focus="open = true"
      @input="open = true; active = -1"
      @keydown="handleKeydown"
      @blur="setTimeout(() => { open = false }, 200)"
    />
    <button
      type="button"
      aria-label="Afficher la liste des produits"
      :aria-expanded="open"
      class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-full text-muted transition hover:text-ink cursor-pointer"
      @click="open = !open"
    >
      <ChevronDown :class="['size-4 transition', open ? 'rotate-180' : '']" aria-hidden="true" />
    </button>

    <!-- Dropdown list -->
    <div
      v-if="open"
      class="absolute top-[calc(100%+0.5rem)] left-0 z-50 w-[28rem] max-w-[calc(100vw-2rem)] min-w-full overflow-hidden rounded-2xl border border-line bg-white shadow-pop"
    >
      <ul role="listbox" aria-label="Tous les produits" class="max-h-80 overflow-y-auto overscroll-contain py-1.5">
        <li
          v-for="(item, i) in matches"
          :key="item.slug"
          role="option"
          :aria-selected="i === active"
          :class="[
            'flex cursor-pointer items-center justify-between gap-3 px-4 py-2.5 text-sm transition',
            i === active ? 'bg-brand-soft' : 'hover:bg-slate-50',
          ]"
          @mouseenter="active = i"
          @mousedown.prevent="pick(item.slug)"
        >
          <span translate="no" class="truncate font-semibold text-ink">
            {{ item.title }}
          </span>
          <span v-if="item.shelf" class="shrink-0 text-xs text-muted">
            {{ item.shelf }}
          </span>
        </li>
        <li v-if="!matches.length" role="presentation" class="px-4 py-3 text-sm text-muted">
          Aucun produit ne correspond
        </li>
      </ul>
    </div>
  </form>

  <!-- MOBILE SEARCH TRIGGER & OVERLAY -->
  <div v-else>
    <button
      type="button"
      aria-label="Rechercher"
      class="flex size-10 items-center justify-center rounded-full border border-line bg-white text-ink shadow-sm"
      @click="openMobile"
    >
      <Search class="size-5" />
    </button>

    <!-- Fullscreen Mobile Search Modal -->
    <Teleport to="body">
      <div
        v-if="mobileOpen"
        class="fixed inset-0 z-50 flex flex-col bg-white p-4 animate-fade-up"
      >
        <div class="flex items-center gap-2 border-b border-line pb-3">
          <div class="relative flex-1">
            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
            <input
              ref="inputRef"
              type="search"
              v-model="query"
              placeholder="Rechercher un produit…"
              class="input min-h-11 rounded-xl bg-canvas pr-4 pl-9 text-base"
              @keydown.enter="submitSearch"
            />
          </div>
          <button
            type="button"
            class="flex size-11 items-center justify-center rounded-xl bg-slate-100 text-ink"
            @click="mobileOpen = false"
          >
            <X class="size-5" />
          </button>
        </div>

        <ul class="flex-1 overflow-y-auto py-3 divide-y divide-slate-100">
          <li
            v-for="item in matches"
            :key="item.slug"
            class="flex items-center justify-between py-3 px-2 text-base font-semibold text-ink active:bg-slate-50"
            @click="pick(item.slug)"
          >
            <span>{{ item.title }}</span>
            <span v-if="item.shelf" class="text-xs text-muted font-normal">{{ item.shelf }}</span>
          </li>
          <li v-if="!matches.length" class="py-6 text-center text-sm text-muted">
            Aucun produit ne correspond
          </li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>
