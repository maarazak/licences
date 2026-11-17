<script setup lang="ts">
import { computed, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
  UserRound,
  ShoppingBag,
  Package,
  LifeBuoy,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import LogoLockup from '@/Components/Shared/LogoLockup.vue';
import HeaderSearch from '@/Components/Navigation/HeaderSearch.vue';
import CurrencySwitch from '@/Components/Navigation/CurrencySwitch.vue';
import LanguageSwitch from '@/Components/Navigation/LanguageSwitch.vue';
import AccountMenu from '@/Components/Navigation/AccountMenu.vue';
import PaymentLogos from '@/Components/Shared/PaymentLogos.vue';
import { NAV_LINKS, SITE_NAME } from '@/Utils/site';
import { useI18n } from '@/Utils/useI18n';

const { t } = useI18n();

const page = usePage();
const authUser = computed(() => page.props.auth?.user);
const notice = computed(() => page.props.notice as string | undefined);
const searchItems = computed(() => (page.props.searchItems as any[]) || []);

// Flash messages handler with SweetAlert2
watch(
  () => page.props.flash,
  (newFlash: any) => {
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

const columns = [
  {
    title: 'Boutique',
    links: [
      { href: '/boutique', label: 'Toutes les offres' },
      { href: '/comment-ca-marche', label: 'Comment ça marche' },
      { href: '/assistance', label: 'Assistance' },
    ],
  },
  {
    title: 'Informations',
    links: [
      { href: '/conditions', label: 'Conditions de vente' },
      { href: '/confidentialite', label: 'Confidentialité' },
      { href: '/mentions-legales', label: 'Mentions légales' },
    ],
  },
];

const bottomLinks = [
  { href: '/boutique', label: 'Boutique', icon: ShoppingBag },
  { href: '/compte/achats', label: 'Mes achats', icon: Package },
  { href: '/assistance', label: 'Assistance', icon: LifeBuoy },
  { href: '/compte', label: 'Compte', icon: UserRound },
];
</script>

<template>
  <div class="min-h-dvh flex flex-col bg-canvas text-ink selection:bg-brand-bright/30">
    <!-- Header sticky avec fond flouté -->
    <header class="sticky top-0 z-40 border-b border-line/70 bg-white/90 backdrop-blur-xl">
      <p v-if="notice" class="bg-brand px-4 py-2 text-center text-xs font-semibold text-white">
        {{ notice }}
      </p>

      <div class="container-page flex min-h-16 items-center gap-3 py-2 lg:gap-4">
        <!-- Logo -->
        <Link href="/" class="inline-flex" :aria-label="SITE_NAME">
          <LogoLockup />
        </Link>

        <!-- Navigation principale desktop -->
        <nav aria-label="Navigation principale" class="hidden items-center gap-1 lg:flex">
          <Link
            v-for="link in NAV_LINKS"
            :key="link.href"
            :href="link.href"
            :class="[
              'rounded-full px-4 py-2 text-sm font-semibold transition hover:bg-ink/5 hover:text-ink',
              $page.url.startsWith(link.href) ? 'bg-ink/5 text-ink' : 'text-ink/75',
            ]"
          >
            {{ t(link.label) }}
          </Link>
        </nav>

        <!-- Barre de recherche desktop avec liste déroulante -->
        <div class="hidden min-w-0 max-w-md flex-1 lg:block">
          <HeaderSearch mode="desktop" :items="searchItems" />
        </div>

        <!-- Actions de droite -->
        <div class="ml-auto flex items-center gap-2">
          <div class="hidden items-center gap-2 lg:flex">
            <CurrencySwitch />
            <LanguageSwitch />
          </div>

          <div class="lg:hidden">
            <HeaderSearch mode="mobile" :items="searchItems" />
          </div>

          <Link
            v-if="!authUser"
            href="/connexion"
            class="btn btn-primary btn-sm hidden lg:inline-flex"
          >
            <UserRound class="size-4" aria-hidden="true" />
            {{ t('Se connecter') }}
          </Link>

          <AccountMenu
            :signedIn="!!authUser"
            :email="authUser?.email"
            :phoneOnly="!authUser"
          >
            <CurrencySwitch />
            <LanguageSwitch />
          </AccountMenu>
        </div>
      </div>
    </header>

    <!-- Contenu principal -->
    <main class="flex-1">
      <slot />
    </main>

    <!-- Navigation mobile flottante en bas -->
    <nav
      aria-label="Navigation mobile"
      class="fixed inset-x-3 bottom-3 z-40 grid grid-cols-4 rounded-2xl border border-line bg-white/95 p-1 shadow-[0_12px_40px_-8px_rgb(11_18_32/0.25)] backdrop-blur-xl lg:hidden"
    >
      <Link
        v-for="link in bottomLinks"
        :key="link.href"
        :href="link.href"
        class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[11px] font-bold text-ink transition active:bg-ink/5"
      >
        <component :is="link.icon" class="size-5" aria-hidden="true" />
        {{ link.label }}
      </Link>
    </nav>

    <!-- Pied de page -->
    <footer class="mt-20 border-t border-line bg-white pb-28 lg:pb-0">
      <div class="container-page grid gap-10 py-12 md:grid-cols-[1.6fr_repeat(2,1fr)]">
        <div class="max-w-xs">
          <Link href="/" :aria-label="SITE_NAME">
            <LogoLockup />
          </Link>
          <p class="mt-4 text-sm leading-relaxed text-muted">
            Vos abonnements premium, payés en FCFA et livrés en quelques minutes.
          </p>
        </div>

        <nav v-for="column in columns" :key="column.title" :aria-label="t(column.title)">
          <p class="text-xs font-bold tracking-[0.14em] text-ink uppercase">
            {{ t(column.title) }}
          </p>
          <ul class="mt-4 space-y-2.5">
            <li v-for="link in column.links" :key="link.href">
              <Link :href="link.href" class="text-sm text-muted transition hover:text-ink">
                {{ t(link.label) }}
              </Link>
            </li>
          </ul>
        </nav>
      </div>

      <div class="border-t border-line">
        <div class="container-page flex flex-col gap-5 py-6 text-xs leading-relaxed text-muted sm:flex-row sm:items-center sm:justify-between">
          <p class="whitespace-nowrap">
            © {{ new Date().getFullYear() }} {{ SITE_NAME }}
          </p>
          <div class="flex flex-col gap-2.5 sm:items-end">
            <p class="text-[11px] font-bold tracking-[0.14em] text-ink uppercase">
              {{ t('Moyens de paiement') }}
            </p>
            <PaymentLogos layout="row" />
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>
