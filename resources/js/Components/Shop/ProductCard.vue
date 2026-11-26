<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ShoppingCart, ArrowRight } from 'lucide-vue-next';
import type { LogoSpec } from '@/Types/logo';
import BrandLogo from '@/Components/Shared/BrandLogo.vue';
import BrandTint from '@/Components/Shared/BrandTint.vue';
import { productLogo } from '@/Utils/brands';
import { deliveryKindLabel } from '@/Types/enums';
import { useI18n } from '@/Utils/useI18n';

export interface ProductItem {
  id: string;
  slug: string;
  name: string;
  title?: string;
  display_name?: string;
  category?: string;
  shop_category?: string;
  shelf?: string;
  price_xof: number;
  priceXof?: number;
  delivery_kind?: string;
  accessKind?: string;
  in_stock?: boolean;
  stock?: number | null;
  preorder?: boolean;
  available?: boolean;
  warranty?: string;
  expire_days?: number;
  duration?: string;
  logo?: LogoSpec;
}

const props = withDefaults(
  defineProps<{
    product: ProductItem;
    priority?: boolean;
  }>(),
  {
    priority: false,
  }
);

const displayTitle = computed(() => props.product.title || props.product.display_name || props.product.name);
const displayShelf = computed(() => props.product.shelf || props.product.shop_category || props.product.category || '');
const displayPriceXof = computed(() => props.product.priceXof ?? props.product.price_xof ?? 0);
const isAvail = computed(() => props.product.available ?? (props.product.in_stock !== false && !props.product.preorder));

const resolvedLogo = computed<LogoSpec>(() => {
  if (props.product.logo) return props.product.logo;
  return productLogo(props.product);
});

const accessKindText = computed(() => {
  const raw = props.product.accessKind || props.product.delivery_kind || props.product.category || '';
  return deliveryKindLabel(raw);
});

const durationText = computed(() => {
  if (props.product.duration && !/warranty/i.test(props.product.duration)) return props.product.duration;
  if (props.product.expire_days) {
    if (props.product.expire_days >= 365) return '1 an';
    if (props.product.expire_days === 30) return '1 mois';
    return `${props.product.expire_days} jours`;
  }
  return 'Selon offre';
});

const { t, format } = useI18n();
</script>

<template>
  <div
    :class="[
      'group relative flex flex-col overflow-hidden rounded-3xl border bg-white shadow-card transition duration-300 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/40 hover:-translate-y-1.5 hover:shadow-pop',
      priority ? 'border-brand-bright/40' : 'border-line hover:border-brand-bright/40',
    ]"
  >
    <!-- Bannière supérieure avec voile coloré de la marque -->
    <div class="relative h-28">
      <div class="absolute inset-0 overflow-hidden">
        <BrandTint :logo="resolvedLogo" :strength="0.15" />
        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-b from-white/0 via-white/5 to-white" />
        <div aria-hidden="true" class="absolute -top-10 -right-6 size-32 rounded-full bg-white/60 blur-2xl" />
      </div>

      <!-- Rayon / Catégorie -->
      <span
        v-if="displayShelf"
        class="absolute top-3.5 left-4 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-bold text-ink/70 shadow-sm"
      >
        {{ t(displayShelf) }}
      </span>

      <!-- Pastille de disponibilité (StockBadge) -->
      <div class="absolute top-3 right-3.5">
        <span v-if="!isAvail" class="badge badge-danger">
          {{ product.preorder ? t('Sur commande') : t('Rupture') }}
        </span>
        <span v-else-if="product.stock === null || product.stock === undefined" class="badge badge-success">
          {{ t('Disponible') }}
        </span>
        <span v-else-if="product.stock <= 3" class="badge badge-warning">
          {{ t('Plus que {n}', { n: product.stock }) }}
        </span>
        <span v-else class="badge badge-success">
          {{ t('{n} en stock', { n: product.stock }) }}
        </span>
      </div>

      <!-- Logo de la marque qui dépasse de la bannière -->
      <BrandLogo
        :logo="resolvedLogo"
        size="lg"
        class="absolute -bottom-8 left-4 transition duration-300 group-hover:scale-105"
      />
    </div>

    <!-- Contenu de la fiche -->
    <div class="flex flex-1 flex-col gap-3 px-4 pt-11 pb-4">
      <div>
        <h3 translate="no" class="h3 line-clamp-2 text-[15px] leading-snug">
          <Link
            :href="`/boutique/${product.slug}`"
            class="outline-none after:absolute after:inset-0 after:z-0 after:content-[''] hover:text-brand transition"
          >
            {{ displayTitle }}
          </Link>
        </h3>
        <div class="mt-2 flex flex-wrap gap-1.5">
          <span class="badge badge-neutral">{{ t(durationText) }}</span>
          <span class="badge badge-neutral">{{ t(accessKindText) }}</span>
        </div>
      </div>

      <!-- Prix & Action Acheter -->
      <div class="mt-auto flex flex-wrap items-center justify-between gap-x-3 gap-y-2 pt-2 border-t border-line/40">
        <p class="font-display text-xl font-extrabold tracking-tight whitespace-nowrap text-ink">
          {{ format(displayPriceXof) }}
        </p>
        <Link
          v-if="isAvail"
          :href="`/commande/validation?produit=${encodeURIComponent(product.slug)}`"
          :aria-label="`${t('Acheter')} : ${displayTitle}`"
          class="btn btn-primary btn-sm relative z-10 shrink-0"
        >
          <ShoppingCart class="size-4" aria-hidden="true" />
          <span>{{ t('Acheter') }}</span>
        </Link>
        <span
          v-else
          aria-hidden="true"
          class="flex size-9 items-center justify-center rounded-full bg-ink text-white transition duration-300 group-hover:bg-accent group-hover:shadow-glow"
        >
          <ArrowRight class="size-4" />
        </span>
      </div>
    </div>
  </div>
</template>
