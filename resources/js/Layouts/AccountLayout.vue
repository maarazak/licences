<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import {
  LayoutDashboard,
  Package,
  KeyRound,
  Wallet,
  Bell,
  LifeBuoy,
  UserRound,
  LogOut,
} from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';

const page = usePage();
const authUser = computed(() => page.props.auth?.user);

const items = [
  { href: '/compte', label: 'Tableau de bord', icon: LayoutDashboard, exact: true },
  { href: '/compte/achats', label: 'Mes achats', icon: Package },
  { href: '/compte/acces', label: 'Mes accès', icon: KeyRound },
  { href: '/compte/solde', label: 'Mon solde', icon: Wallet },
  { href: '/compte/alertes', label: 'Mes alertes', icon: Bell },
  { href: '/compte/assistance', label: 'Assistance', icon: LifeBuoy },
  { href: '/compte/profil', label: 'Profil', icon: UserRound },
];

const handleLogout = () => {
  router.post('/deconnexion');
};
</script>

<template>
  <SiteLayout>
    <div class="container-page grid gap-6 py-8 lg:grid-cols-[240px_1fr] lg:gap-10">
      <!-- Sidebar -->
      <aside class="min-w-0 lg:sticky lg:top-24 lg:self-start">
        <div v-if="authUser" class="mb-4 flex items-center gap-3 bg-white p-3.5 rounded-2xl border border-slate-200/80 shadow-sm">
          <span
            aria-hidden="true"
            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#19c39a] to-[#0d5c4a] font-display text-base font-bold text-white shadow-sm"
          >
            {{ (authUser.email || 'U').charAt(0).toUpperCase() }}
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-semibold text-slate-500">{{ authUser.email }}</p>
            <p class="text-sm font-extrabold text-[#0d5c4a] mt-0.5">
              {{ (authUser.balance_xof || 0).toLocaleString('fr-FR') }} FCFA
            </p>
          </div>
        </div>

        <nav class="bg-white rounded-2xl border border-slate-200/80 p-2 shadow-sm space-y-1">
          <Link
            v-for="item in items"
            :key="item.href"
            :href="item.href"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition',
              (item.exact ? $page.url === item.href : $page.url.startsWith(item.href))
                ? 'bg-[#0d5c4a] text-white shadow-sm'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
            ]"
          >
            <component :is="item.icon" class="size-4 shrink-0" />
            <span>{{ item.label }}</span>
          </Link>

          <button
            type="button"
            @click="handleLogout"
            class="flex w-full items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold text-red-600 hover:bg-red-50 transition pt-2 border-t border-slate-100"
          >
            <LogOut class="size-4 shrink-0" />
            <span>Se déconnecter</span>
          </button>
        </nav>
      </aside>

      <!-- Main Account Content -->
      <div class="min-w-0">
        <slot />
      </div>
    </div>
  </SiteLayout>
</template>
