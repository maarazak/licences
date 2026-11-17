<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
  BadgeCheck,
  ChevronDown,
  KeyRound,
  LifeBuoy,
  Package,
  RefreshCw,
  ShieldCheck,
  ShoppingBag,
  Smartphone,
  Zap,
} from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import BrandMarquee from '@/Components/Shared/BrandMarquee.vue';
import CategoryBubble from '@/Components/Shop/CategoryBubble.vue';
import HeroCarousel, { type HeroSlide } from '@/Components/Shop/HeroCarousel.vue';
import LoadMoreLink from '@/Components/Shop/LoadMoreLink.vue';
import PaymentLogos from '@/Components/Shared/PaymentLogos.vue';
import ProductCard from '@/Components/Shop/ProductCard.vue';
import { SITE_NAME } from '@/Utils/site';
import { logoFor, distinctBrands, diversify } from '@/Utils/brands';
import { useI18n } from '@/Utils/useI18n';
import type { LogoSpec } from '@/Types/logo';

export interface CatalogProduct {
  id: string;
  slug: string;
  name: string;
  title: string;
  display_name?: string;
  shelf: string;
  category: string;
  shop_category?: string;
  image_url?: string;
  price_xof: number;
  priceXof: number;
  in_stock: boolean;
  stock?: number | null;
  preorder: boolean;
  available: boolean;
  delivery_kind: string;
  accessKind: string;
  duration: string;
  warranty?: string;
  is_featured: boolean;
  featured_rank?: number | null;
  logo?: LogoSpec;
}

const props = defineProps<{
  products: CatalogProduct[];
  featuredRaw: CatalogProduct[];
  availableProducts: CatalogProduct[];
  categories: [string, number][];
  initialFeaturedCount?: number;
}>();

const FEATURED_PAGE = 8;
const featuredCount = ref(props.initialFeaturedCount || FEATURED_PAGE);

// Décorer chaque produit avec son LogoSpec
const decorateWithLogo = (p: CatalogProduct): CatalogProduct => ({
  ...p,
  logo: p.logo || logoFor(p.name, p.display_name),
});

const available = computed(() => (props.availableProducts || []).map(decorateWithLogo));
const featuredRawList = computed(() => (props.featuredRaw || []).map(decorateWithLogo));

// Produits phares d'abord ; à défaut de sélection, un choix varié de marques.
const featured = computed(() => {
  if (featuredRawList.value.length >= 4) {
    return featuredRawList.value;
  }
  return diversify(available.value, 8);
});

const featuredIds = computed(() => new Set(featured.value.map((p) => p.id)));

// Section « Les plus demandés » : phares d'abord, puis les autres offres disponibles (sans doublon)
const pool = computed(() => {
  const others = available.value.filter((p) => !featuredIds.value.has(p.id));
  return featured.value.concat(others);
});

// Marques pour le bandeau défilant BrandMarquee
const brands = computed(() => {
  const allLogos = featured.value.concat(available.value).map((p) => p.logo).filter(Boolean) as LogoSpec[];
  return distinctBrands(allLogos, 16);
});

const { t, format } = useI18n();

function stockBadge(p: CatalogProduct): { badge: string; badgeTone: 'success' | 'warning' | 'danger' } {
  if (!p.available) return { badge: t('Rupture'), badgeTone: 'danger' };
  if (p.stock !== null && p.stock !== undefined && p.stock <= 3) {
    return { badge: t('Plus que {n}', { n: p.stock }), badgeTone: 'warning' };
  }
  return { badge: t('Disponible'), badgeTone: 'success' };
}

// Slides pour le HeroCarousel 3D
const slides = computed<HeroSlide[]>(() => {
  return featured.value.slice(0, 6).map((p) => ({
    slug: p.slug,
    title: p.title,
    duration: t(p.duration),
    price: format(p.priceXof),
    shelf: p.shelf ? t(p.shelf) : '',
    logo: p.logo!,
    ...stockBadge(p),
  }));
});

const reasons = computed(() => [
  { icon: BadgeCheck, title: t('Prix final en FCFA'), text: t('Aucun frais ajouté au moment de payer.'), gradient: 'from-orange-400 to-accent' },
  { icon: KeyRound, title: t('Accès dans votre espace'), text: t('Retrouvez-les à tout moment dans Mes achats.'), gradient: 'from-emerald-400 to-brand' },
  { icon: LifeBuoy, title: t('Aide liée à votre commande'), text: t('Chaque demande est suivie jusqu’à sa résolution.'), gradient: 'from-sky-400 to-indigo-500' },
  { icon: RefreshCw, title: t('Avoir ou remboursement'), text: t('Si la livraison échoue, vous choisissez.'), gradient: 'from-violet-400 to-fuchsia-500' },
]);

const steps = computed(() => [
  { icon: ShoppingBag, title: t('Choisissez votre offre'), text: t('Type d’accès, durée et garantie sont affichés avant de payer.') },
  { icon: Smartphone, title: t('Payez en FCFA'), text: t('Mobile Money (Orange, Moov, MTN, Wave) en toute sécurité.') },
  { icon: Package, title: t('Recevez votre accès'), text: t('Livraison automatique dans Mes achats, avec un e-mail de confirmation.') },
]);

const faq = computed(() => [
  [t('Combien de temps pour recevoir mon accès ?'), t('En général quelques minutes après la confirmation du paiement. Certaines offres demandent une activation manuelle, indiquée sur la fiche.')],
  [t('Comment payer ?'), t('Par mobile money, sur la page sécurisée de notre prestataire de paiement. Le prix affiché est le prix final, en FCFA.')],
  [t('Et si mon accès ne fonctionne pas ?'), t('Signalez-le depuis votre commande pendant la durée de garantie : nous le remplaçons ou nous créditons votre solde.')],
  [t('Où retrouver mes accès ?'), t('Dans Mes achats, à tout moment, après connexion par code e-mail. Aucun mot de passe à retenir.')],
]);

const trust = computed(() => [
  { icon: Zap, text: t('Livraison en quelques minutes') },
  { icon: ShieldCheck, text: t('Paiement mobile money sécurisé') },
  { icon: RefreshCw, text: t('Remplacé ou crédité si l’accès échoue') },
]);

const handleLoadMore = () => {
  featuredCount.value += FEATURED_PAGE;
};
</script>

<template>
  <SiteLayout>
    <Head title="Abonnements et licences numériques payés en FCFA" />

    <!-- ── Héros ── -->
    <section class="bg-hero relative overflow-hidden">
      <div aria-hidden="true" class="grid-lines absolute inset-0" />
      <div class="container-page relative grid gap-12 py-14 sm:py-18 lg:grid-cols-[1fr_1.05fr] lg:items-center lg:gap-10 lg:py-20">
        <div class="animate-fade-up">
          <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
            <p class="inline-flex items-center gap-2.5 rounded-full border border-line bg-white px-4 py-1.5 text-xs font-bold text-ink/80 shadow-sm">
              <span class="pulse-dot size-2 rounded-full bg-brand-bright" />
              Livraison en quelques minutes
            </p>
            <PaymentLogos layout="floating" />
          </div>
          <h1 class="mt-6 font-display text-[2.6rem] leading-[1.04] font-extrabold tracking-tight text-ink sm:text-6xl lg:text-[4rem]">
            Vos abonnements premium, <span class="text-gradient">payés en FCFA.</span>
          </h1>
          <p class="mt-6 max-w-lg text-lg leading-relaxed text-muted">
            Netflix, ChatGPT, Claude, Canva et bien plus. Payez par mobile money, recevez votre accès en quelques minutes.
          </p>
          <div class="mt-8 flex flex-wrap gap-3">
            <a href="#phares" class="btn btn-primary min-h-12 px-7 text-base">
              {{ t('Voir les offres') }}
            </a>
            <Link href="/boutique" class="btn btn-soft min-h-12 px-7 text-base">
              {{ t('Toute la boutique') }}
            </Link>
          </div>
          <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold text-ink/75">
            <li v-for="item in trust" :key="item.text" class="flex items-center gap-2">
              <component :is="item.icon" class="size-4 text-brand" aria-hidden="true" />
              <span>{{ item.text }}</span>
            </li>
          </ul>
        </div>
        <div class="animate-fade-up delay-2">
          <HeroCarousel :slides="slides" :cta="t('Voir l\'offre')" />
        </div>
      </div>
      <div class="relative border-y border-line bg-white/70">
        <BrandMarquee :logos="brands" :label="t('Marques proposées')" />
      </div>
    </section>

    <!-- ── Produits phares ── -->
    <section id="phares" class="container-page scroll-mt-24 pt-16">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p class="eyebrow text-accent-ink">{{ t('Les plus demandés') }}</p>
          <h2 class="h2 mt-1">{{ t('Produits phares') }}</h2>
        </div>
        <Link href="/boutique" class="link text-sm">
          {{ t('Toute la boutique') }}
        </Link>
      </div>

      <template v-if="featured.length">
        <div class="mt-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          <ProductCard
            v-for="product in pool.slice(0, featuredCount)"
            :key="product.id"
            :product="product"
            :priority="featuredIds.has(product.id)"
          />
        </div>
        <LoadMoreLink
          v-if="pool.length > featuredCount"
          :href="`/?phares=${featuredCount + FEATURED_PAGE}#phares`"
          label="Charger plus"
          :hint="`${featuredCount} sur ${pool.length} offres affichées`"
          @click.prevent="handleLoadMore"
        />
      </template>
      <p v-else class="card mt-7 px-6 py-12 text-center text-sm text-muted">
        Les offres arrivent bientôt. Revenez dans un instant.
      </p>
    </section>

    <!-- ── Catégories ── -->
    <section v-if="categories && categories.length" class="container-page pt-14">
      <h2 class="h3 text-lg">{{ t('Parcourir par catégorie') }}</h2>
      <div class="mt-4 flex flex-wrap gap-2.5">
        <Link
          v-for="[name, count] in categories.slice(0, 8)"
          :key="name"
          :href="`/boutique?categorie=${encodeURIComponent(name)}`"
          class="chip"
        >
          <CategoryBubble :name="name" size="sm" />
          <span>{{ t(name) }}</span>
          <span class="text-xs font-medium text-muted">{{ count }}</span>
        </Link>
      </div>
    </section>

    <!-- ── Pourquoi nous ── -->
    <section class="container-page pt-16">
      <h2 class="h2">{{ t('Pourquoi acheter chez {name}', { name: SITE_NAME }) }}</h2>
      <div class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="item in reasons" :key="item.title" class="card p-5">
          <span
            :class="[
              'flex size-11 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-[0_10px_20px_-10px_rgb(11_18_32/0.5)]',
              item.gradient,
            ]"
          >
            <component :is="item.icon" class="size-5" aria-hidden="true" />
          </span>
          <h3 class="h3 mt-4 text-[15px]">{{ item.title }}</h3>
          <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ item.text }}</p>
        </div>
      </div>
    </section>

    <!-- ── Comment ça marche ── -->
    <section class="container-page pt-16">
      <h2 class="h2">{{ t('Comment ça marche') }}</h2>
      <ol class="mt-7 grid gap-4 md:grid-cols-3">
        <li v-for="(step, i) in steps" :key="step.title" class="card flex gap-4 p-5">
          <span
            class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-bright to-brand text-white shadow-[0_14px_28px_-10px_rgb(13_92_74/0.7)]"
          >
            <component :is="step.icon" class="size-6" aria-hidden="true" />
          </span>
          <div>
            <p class="text-xs font-bold text-accent-ink">{{ t('Étape {n}', { n: i + 1 }) }}</p>
            <h3 class="h3 mt-0.5">{{ step.title }}</h3>
            <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ step.text }}</p>
          </div>
        </li>
      </ol>
    </section>

    <!-- ── Questions fréquentes ── -->
    <section class="container-page max-w-3xl pt-16">
      <h2 class="h2">{{ t('Questions fréquentes') }}</h2>
      <div class="mt-6 space-y-2.5">
        <details v-for="[question, answer] in faq" :key="question" class="card group px-5 py-4">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-bold">
            <span>{{ question }}</span>
            <ChevronDown
              class="size-4 shrink-0 text-muted transition-transform group-open:rotate-180"
              aria-hidden="true"
            />
          </summary>
          <p class="mt-2.5 text-sm leading-relaxed text-muted">{{ answer }}</p>
        </details>
      </div>
    </section>
  </SiteLayout>
</template>
