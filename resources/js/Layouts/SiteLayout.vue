<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import {
  UserRound,
  ShoppingBag,
  Package,
  LifeBuoy,
  LayoutDashboard,
  Wallet,
  LogOut,
  Search,
  X,
  ChevronRight,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import LogoLockup from '@/Components/Navigation/LogoLockup.vue';
import PaymentLogos from '@/Components/Shared/PaymentLogos.vue';
import { NAV_LINKS, SITE_NAME } from '@/Utils/site';

const page = usePage();
const authUser = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash || {});

// User Dropdown menu
const menuOpen = ref(false);
const searchQuery = ref('');
const searchOpen = ref(false);

const toggleMenu = () => {
  menuOpen.value = !menuOpen.value;
};

const closeMenu = () => {
  menuOpen.value = false;
};

const handleLogout = () => {
  closeMenu();
  router.post('/deconnexion');
};

const handleSearch = () => {
  if (searchQuery.value.trim()) {
    router.get('/boutique', { q: searchQuery.value.trim() }, { preserveState: true });
    searchOpen.value = false;
  }
};

// Flash messages handler with SweetAlert2
watch(
  () => page.props.flash,
  (newFlash) => {
    if (newFlash?.success) {
      Swal.fire({
        icon: 'success',
        title: newFlash.success,
        timer: 3500,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
      });
    }
    if (newFlash?.error) {
      Swal.fire({
        icon: 'error',
        title: 'Erreur',
        text: newFlash.error,
        confirmButtonColor: '#0d5c4a',
      });
    }
  },
  { deep: true, immediate: true }
);
</script>

<template>
  <div class="min-h-dvh flex flex-col bg-[#f4f6fb] text-slate-900 selection:bg-[#19c39a]/30">
    <!-- Header -->
    <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
      <div class="container-page flex min-h-16 items-center gap-3 py-2 lg:gap-4">
        <!-- Logo -->
        <Link href="/" class="inline-flex shrink-0">
          <LogoLockup />
        </Link>

        <!-- Navigation Links -->
        <nav aria-label="Navigation principale" class="hidden items-center gap-1 lg:flex ml-4">
          <Link
            v-for="link in NAV_LINKS"
            :key="link.href"
            :href="link.href"
            :class="[
              'rounded-full px-4 py-2 text-sm font-semibold transition',
              $page.url.startsWith(link.href)
                ? 'bg-slate-100 text-slate-900'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
            ]"
          >
            {{ link.label }}
          </Link>
        </nav>

        <!-- Desktop Search Bar -->
        <div class="hidden min-w-0 max-w-md flex-1 lg:block mx-4">
          <form @submit.prevent="handleSearch" class="relative">
            <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
            <input
              v-model="searchQuery"
              type="search"
              placeholder="Rechercher un produit (ChatGPT, Canva, Netflix...)"
              class="w-full rounded-full border border-slate-200 bg-slate-50/70 py-2 pl-10 pr-4 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#0d5c4a] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#0d5c4a]/15 transition"
            />
          </form>
        </div>

        <!-- Right actions -->
        <div class="ml-auto flex items-center gap-2">
          <!-- Mobile search toggle -->
          <button
            type="button"
            @click="searchOpen = !searchOpen"
            class="flex size-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 lg:hidden shadow-sm active:scale-95 transition"
          >
            <Search class="size-4" />
          </button>

          <!-- Connexion Button (Desktop non connecté) -->
          <Link
            v-if="!authUser"
            href="/connexion"
            class="btn btn-primary btn-sm hidden lg:inline-flex"
          >
            <UserRound class="size-4" />
            Se connecter
          </Link>

          <!-- Account Dropdown Menu -->
          <div class="relative">
            <button
              type="button"
              @click="toggleMenu"
              :class="[
                'flex size-10 lg:size-11 items-center justify-center rounded-full shadow-sm transition active:scale-95',
                authUser
                  ? 'bg-[#0d5c4a] text-white hover:bg-[#083f33]'
                  : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
              ]"
              title="Menu du compte"
            >
              <UserRound class="size-5" />
            </button>

            <!-- Backdrop to close -->
            <div
              v-if="menuOpen"
              @click="closeMenu"
              class="fixed inset-0 z-40 bg-black/5"
            />

            <!-- Panel -->
            <div
              v-if="menuOpen"
              class="absolute right-0 top-[calc(100%+0.5rem)] z-50 w-72 max-w-[calc(100vw-1.5rem)] rounded-2xl border border-slate-200 bg-white p-2.5 shadow-xl animate-fade-up"
            >
              <template v-if="authUser">
                <div class="px-3 pt-2 pb-2.5 border-b border-slate-100">
                  <p class="truncate text-xs font-semibold text-slate-500">{{ authUser.email }}</p>
                  <p class="text-sm font-bold text-[#0d5c4a] mt-0.5">
                    Solde : {{ (authUser.balance_xof || 0).toLocaleString('fr-FR') }} FCFA
                  </p>
                </div>

                <nav class="py-1 space-y-0.5">
                  <Link
                    href="/compte"
                    @click="closeMenu"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-100 transition"
                  >
                    <LayoutDashboard class="size-4 text-slate-400" />
                    Tableau de bord
                  </Link>

                  <Link
                    href="/compte/achats"
                    @click="closeMenu"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-100 transition"
                  >
                    <Package class="size-4 text-slate-400" />
                    Mes achats
                  </Link>

                  <Link
                    href="/compte/solde"
                    @click="closeMenu"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-100 transition"
                  >
                    <Wallet class="size-4 text-slate-400" />
                    Mon solde (Recharger)
                  </Link>

                  <Link
                    href="/compte/assistance"
                    @click="closeMenu"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-100 transition"
                  >
                    <LifeBuoy class="size-4 text-slate-400" />
                    Assistance & Tickets
                  </Link>

                  <Link
                    href="/compte/profil"
                    @click="closeMenu"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-100 transition"
                  >
                    <UserRound class="size-4 text-slate-400" />
                    Profil & Réglages
                  </Link>
                </nav>

                <div class="pt-1 border-t border-slate-100">
                  <button
                    type="button"
                    @click="handleLogout"
                    class="flex w-full items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-red-600 hover:bg-red-50 transition"
                  >
                    <LogOut class="size-4" />
                    Se déconnecter
                  </button>
                </div>
              </template>

              <template v-else>
                <div class="p-2 space-y-3">
                  <p class="text-xs text-slate-500 leading-relaxed">
                    Connectez-vous pour retrouver vos licences, votre solde et suivre vos commandes.
                  </p>
                  <Link
                    href="/connexion"
                    @click="closeMenu"
                    class="btn btn-primary btn-block min-h-11"
                  >
                    <UserRound class="size-4" />
                    Se connecter
                  </Link>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Mobile Search Form -->
      <div v-if="searchOpen" class="p-3 border-t border-slate-200 bg-white lg:hidden">
        <form @submit.prevent="handleSearch" class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Rechercher un produit..."
            class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-9 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#0d5c4a] focus:bg-white focus:outline-none"
            autofocus
          />
          <button
            type="button"
            @click="searchOpen = false"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
          >
            <X class="size-4" />
          </button>
        </form>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1">
      <slot />
    </main>

    <!-- Mobile Bottom Navigation Bar -->
    <nav
      aria-label="Navigation mobile"
      class="fixed inset-x-3 bottom-3 z-40 grid grid-cols-4 rounded-2xl border border-slate-200/90 bg-white/95 p-1 shadow-[0_12px_40px_-8px_rgb(11_18_32/0.25)] backdrop-blur-xl lg:hidden"
    >
      <Link
        href="/boutique"
        :class="[
          'flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[11px] font-bold transition active:bg-slate-100',
          $page.url.startsWith('/boutique') ? 'text-[#0d5c4a]' : 'text-slate-700',
        ]"
      >
        <ShoppingBag class="size-5" />
        Boutique
      </Link>

      <Link
        href="/compte/achats"
        :class="[
          'flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[11px] font-bold transition active:bg-slate-100',
          $page.url.startsWith('/compte/achats') ? 'text-[#0d5c4a]' : 'text-slate-700',
        ]"
      >
        <Package class="size-5" />
        Mes achats
      </Link>

      <Link
        href="/assistance"
        :class="[
          'flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[11px] font-bold transition active:bg-slate-100',
          $page.url.startsWith('/assistance') ? 'text-[#0d5c4a]' : 'text-slate-700',
        ]"
      >
        <LifeBuoy class="size-5" />
        Assistance
      </Link>

      <Link
        href="/compte"
        :class="[
          'flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[11px] font-bold transition active:bg-slate-100',
          $page.url.startsWith('/compte') && !$page.url.startsWith('/compte/achats') ? 'text-[#0d5c4a]' : 'text-slate-700',
        ]"
      >
        <UserRound class="size-5" />
        Compte
      </Link>
    </nav>

    <!-- Footer -->
    <footer class="mt-20 border-t border-slate-200 bg-white pb-28 lg:pb-0">
      <div class="container-page grid gap-10 py-12 md:grid-cols-[1.6fr_repeat(2,1fr)]">
        <div class="max-w-xs">
          <Link href="/">
            <LogoLockup />
          </Link>
          <p class="mt-4 text-sm leading-relaxed text-slate-500">
            Vos abonnements premium, payés en FCFA par Mobile Money et livrés en quelques minutes.
          </p>
        </div>

        <div>
          <p class="text-xs font-bold tracking-[0.14em] text-slate-900 uppercase">Boutique</p>
          <ul class="mt-4 space-y-2.5">
            <li>
              <Link href="/boutique" class="text-sm text-slate-500 hover:text-slate-900 transition">
                Toutes les offres
              </Link>
            </li>
            <li>
              <Link href="/comment-ca-marche" class="text-sm text-slate-500 hover:text-slate-900 transition">
                Comment ça marche
              </Link>
            </li>
            <li>
              <Link href="/assistance" class="text-sm text-slate-500 hover:text-slate-900 transition">
                Assistance & FAQ
              </Link>
            </li>
          </ul>
        </div>

        <div>
          <p class="text-xs font-bold tracking-[0.14em] text-slate-900 uppercase">Informations</p>
          <ul class="mt-4 space-y-2.5">
            <li>
              <Link href="/conditions" class="text-sm text-slate-500 hover:text-slate-900 transition">
                Conditions de vente
              </Link>
            </li>
            <li>
              <Link href="/confidentialite" class="text-sm text-slate-500 hover:text-slate-900 transition">
                Confidentialité
              </Link>
            </li>
            <li>
              <Link href="/mentions-legales" class="text-sm text-slate-500 hover:text-slate-900 transition">
                Mentions légales
              </Link>
            </li>
          </ul>
        </div>
      </div>

      <div class="border-t border-slate-100">
        <div class="container-page flex flex-col gap-5 py-6 text-xs leading-relaxed text-slate-500 sm:flex-row sm:items-center sm:justify-between">
          <p>© {{ new Date().getFullYear() }} {{ SITE_NAME }}. Tous droits réservés.</p>
          <div class="flex flex-col gap-2 sm:items-end">
            <p class="text-[11px] font-bold tracking-[0.14em] text-slate-900 uppercase">Moyens de paiement acceptés</p>
            <PaymentLogos layout="row" />
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>
