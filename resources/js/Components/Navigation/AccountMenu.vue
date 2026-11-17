<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/Utils/useI18n';
import {
  UserRound,
  LayoutDashboard,
  Package,
  Wallet,
  LifeBuoy,
  LogOut,
} from 'lucide-vue-next';

withDefaults(
  defineProps<{
    signedIn?: boolean;
    email?: string;
    phoneOnly?: boolean;
  }>(),
  {
    signedIn: false,
    phoneOnly: false,
  }
);

const open = ref(false);
const rootRef = ref<HTMLDivElement | null>(null);

const onPointer = (event: PointerEvent) => {
  if (rootRef.value && !rootRef.value.contains(event.target as Node)) {
    open.value = false;
  }
};

const onKey = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    open.value = false;
  }
};

onMounted(() => {
  document.addEventListener('pointerdown', onPointer);
  document.addEventListener('keydown', onKey);
});

onUnmounted(() => {
  document.removeEventListener('pointerdown', onPointer);
  document.removeEventListener('keydown', onKey);
});

const close = () => {
  open.value = false;
};

const logout = () => {
  close();
  router.post('/deconnexion');
};

const { t } = useI18n();

const links = computed(() => [
  { href: '/compte', label: t('Tableau de bord'), icon: LayoutDashboard },
  { href: '/compte/achats', label: t('Mes achats'), icon: Package },
  { href: '/compte/solde', label: t('Mon solde'), icon: Wallet },
  { href: '/compte/assistance', label: t('Assistance'), icon: LifeBuoy },
  { href: '/compte/profil', label: t('Profil'), icon: UserRound },
]);

const row =
  'flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-sm font-semibold text-ink transition hover:bg-ink/5 active:bg-ink/5 cursor-pointer';
</script>

<template>
  <div ref="rootRef" :class="['relative', phoneOnly ? 'lg:hidden' : '']">
    <button
      type="button"
      aria-label="Menu du compte"
      :aria-expanded="open"
      :class="[
        'flex size-11 items-center justify-center rounded-full shadow-sm transition active:scale-95 cursor-pointer',
        signedIn ? 'bg-brand text-white hover:bg-brand-strong' : 'border border-line bg-white text-ink',
      ]"
      @click="open = !open"
    >
      <UserRound class="size-5" aria-hidden="true" />
    </button>

    <!-- Dropdown Menu -->
    <div
      v-if="open"
      class="absolute top-[calc(100%+0.5rem)] right-0 z-50 w-72 max-w-[calc(100vw-1.5rem)] rounded-2xl border border-line bg-white p-2 shadow-pop animate-fade-up"
    >
      <!-- CONNECTÉ -->
      <template v-if="signedIn">
        <p v-if="email" class="truncate px-3 pt-1.5 pb-2.5 text-xs font-semibold text-muted" :title="email">
          {{ email }}
        </p>
        <nav aria-label="Menu du compte">
          <Link
            v-for="item in links"
            :key="item.href"
            :href="item.href"
            :class="row"
            @click="close"
          >
            <component :is="item.icon" class="size-4 text-muted" aria-hidden="true" />
            <span>{{ item.label }}</span>
          </Link>
        </nav>
      </template>

      <!-- VISITEUR NON CONNECTÉ -->
      <template v-else>
        <Link href="/connexion" :class="['btn btn-primary btn-block min-h-11']" @click="close">
          <UserRound class="size-4" aria-hidden="true" />
          <span>{{ t('Se connecter') }}</span>
        </Link>
      </template>

      <!-- SLOT PRÉFÉRENCES MOBILE -->
      <div v-if="$slots.default" class="mt-2 space-y-2 border-t border-line px-1 pt-3 pb-1 lg:hidden">
        <p class="px-2 text-[11px] font-bold tracking-[0.14em] text-muted uppercase">{{ t('Préférences') }}</p>
        <div class="flex flex-wrap items-center gap-2 px-2">
          <slot />
        </div>
      </div>

      <!-- DÉCONNEXION -->
      <div v-if="signedIn" class="mt-2 border-t border-line pt-2">
        <button type="button" :class="[row, 'text-danger hover:bg-danger-soft']" @click="logout">
          <LogOut class="size-4" aria-hidden="true" />
          <span>{{ t('Se déconnecter') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
