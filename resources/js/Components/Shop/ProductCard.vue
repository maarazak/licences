<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ShoppingCart, ArrowRight } from 'lucide-vue-next';
import BrandLogo from '@/Components/Shared/BrandLogo.vue';
import { deliveryKindLabel } from '@/Types/enums';

const props = defineProps<{
  product: {
    id: string;
    slug: string;
    name: string;
    display_name?: string;
    category?: string;
    shop_category?: string;
    image_url?: string;
    price_xof: number;
    delivery_kind?: string;
    in_stock?: boolean;
    stock?: number;
    preorder?: boolean;
    warranty?: string;
    expire_days?: number;
  };
  priority?: boolean;
}>();

const title = computed(() => props.product.display_name || props.product.name);
const formattedPrice = computed(() => `${(props.product.price_xof || 0).toLocaleString('fr-FR')} FCFA`);
const isAvailable = computed(() => props.product.in_stock !== false);
const accessLabel = computed(() => deliveryKindLabel(props.product.delivery_kind || props.product.category || ''));
</script>

<template>
  <div
    :class="[
      'group relative flex flex-col overflow-hidden rounded-3xl border bg-white shadow-card transition duration-300 hover:-translate-y-1.5 hover:shadow-pop',
      priority ? 'border-[#19c39a]/40' : 'border-slate-200/80 hover:border-[#19c39a]/40',
    ]"
  >
    <!-- Top banner with protruding logo -->
    <div class="relative h-28 bg-gradient-to-br from-slate-100 to-slate-50 border-b border-slate-100/80">
      <div aria-hidden="true" class="absolute -top-10 -right-6 size-32 rounded-full bg-white/60 blur-2xl" />

      <!-- Category tag -->
      <span
        v-if="product.shop_category || product.category"
        class="absolute top-3.5 left-4 rounded-full bg-white/90 backdrop-blur px-2.5 py-1 text-[11px] font-bold text-slate-700 shadow-sm"
      >
        {{ (product.shop_category || product.category).toUpperCase() }}
      </span>

      <!-- Stock badge -->
      <div class="absolute top-3 right-3.5">
        <span
          v-if="isAvailable"
          class="badge badge-success text-[11px] px-2.5 py-0.5"
        >
          Disponible
        </span>
        <span
          v-else-if="product.preorder"
          class="badge badge-warning text-[11px] px-2.5 py-0.5"
        >
          Sur commande
        </span>
        <span
          v-else
          class="badge badge-danger text-[11px] px-2.5 py-0.5"
        >
          Rupture
        </span>
      </div>

      <!-- Protruding logo -->
      <div class="absolute -bottom-7 left-4 transition duration-300 group-hover:scale-105 z-10">
        <BrandLogo :name="title" :image="product.image_url" size="lg" />
      </div>
    </div>

    <!-- Product Details -->
    <div class="flex flex-1 flex-col gap-3 px-4 pt-10 pb-4">
      <div>
        <h3 class="h3 line-clamp-2 text-[15px] leading-snug">
          <Link
            :href="`/boutique/${product.slug}`"
            class="text-slate-900 group-hover:text-[#0d5c4a] transition outline-none after:absolute after:inset-0 after:z-0 after:content-['']"
          >
            {{ title }}
          </Link>
        </h3>

        <div class="mt-2.5 flex flex-wrap gap-1.5">
          <span v-if="product.expire_days" class="badge badge-neutral">
            {{ product.expire_days }} jours
          </span>
          <span v-else-if="product.warranty" class="badge badge-neutral">
            {{ product.warranty }}
          </span>
          <span class="badge badge-neutral">
            {{ accessLabel }}
          </span>
        </div>
      </div>

      <!-- Price & Buy Button -->
      <div class="mt-auto flex flex-wrap items-center justify-between gap-x-3 gap-y-2 pt-3 border-t border-slate-100">
        <p class="font-display text-xl font-extrabold tracking-tight text-slate-900 whitespace-nowrap">
          {{ formattedPrice }}
        </p>

        <Link
          v-if="isAvailable"
          :href="`/commande/validation?produit=${encodeURIComponent(product.slug)}`"
          class="btn btn-primary btn-sm relative z-10 shrink-0 shadow-sm"
          :aria-label="`Acheter : ${title}`"
        >
          <ShoppingCart class="size-4" />
          Acheter
        </Link>
        <span
          v-else
          aria-hidden="true"
          class="flex size-9 items-center justify-center rounded-full bg-slate-900 text-white transition duration-300 group-hover:bg-[#ff6a13] group-hover:shadow-glow"
        >
          <ArrowRight class="size-4" />
        </span>
      </div>
    </div>
  </div>
</template>
