<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
  ShoppingCart,
  ShieldCheck,
  Zap,
  Clock,
  ArrowLeft,
  CheckCircle2,
  AlertCircle,
  HelpCircle,
} from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import BrandLogo from '@/Components/Shared/BrandLogo.vue';
import ProductCard from '@/Components/Shop/ProductCard.vue';
import { deliveryKindLabel } from '@/Types/enums';

interface ProductDetail {
  id: string;
  slug: string;
  name: string;
  display_name?: string;
  category?: string;
  shop_category?: string;
  image_url?: string;
  price_xof: number;
  in_stock?: boolean;
  stock?: number;
  preorder?: boolean;
  expire_days?: number;
  warranty?: string;
  delivery_kind?: string;
  description?: string;
  instructions?: string;
  requires_email?: boolean;
}

const props = defineProps<{
  product: ProductDetail;
  related: any[];
}>();

const title = computed(() => props.product.display_name || props.product.name);
const formattedPrice = computed(() => `${(props.product.price_xof || 0).toLocaleString('fr-FR')} FCFA`);
const accessKind = computed(() => deliveryKindLabel(props.product.delivery_kind || props.product.category || ''));
const isAvailable = computed(() => props.product.in_stock !== false);
</script>

<template>
  <SiteLayout>
    <Head :title="`${title} — Acheter en FCFA`" />

    <div class="container-page py-10 space-y-12">
      <!-- Breadcrumb / Back link -->
      <div>
        <Link
          href="/boutique"
          class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-900 transition"
        >
          <ArrowLeft class="size-4" />
          Retour à la boutique
        </Link>
      </div>

      <!-- Main Product Presentation -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
        <!-- Left: Visual & Details -->
        <div class="lg:col-span-7 space-y-8">
          <div class="card p-6 sm:p-8 space-y-6">
            <div class="flex items-start gap-5">
              <BrandLogo :name="title" :image="product.image_url" size="xl" />
              <div class="min-w-0 space-y-2">
                <span
                  v-if="product.shop_category || product.category"
                  class="badge badge-neutral uppercase tracking-wider text-[11px]"
                >
                  {{ product.shop_category || product.category }}
                </span>
                <h1 class="h2 text-2xl sm:text-3xl leading-snug">{{ title }}</h1>
              </div>
            </div>

            <!-- Badges characteristics -->
            <div class="flex flex-wrap gap-2 pt-2 border-t border-slate-100">
              <span class="badge badge-neutral px-3 py-1">
                Type : {{ accessKind }}
              </span>
              <span v-if="product.expire_days" class="badge badge-neutral px-3 py-1">
                Validité : {{ product.expire_days }} jours
              </span>
              <span v-if="product.warranty" class="badge badge-neutral px-3 py-1">
                Garantie : {{ product.warranty }}
              </span>
              <span v-if="isAvailable" class="badge badge-success px-3 py-1">
                ✓ En stock immédiat
              </span>
              <span v-else class="badge badge-danger px-3 py-1">
                Rupture temporaire
              </span>
            </div>

            <!-- Description -->
            <div class="pt-4 border-t border-slate-100 space-y-3">
              <h2 class="h3 text-base">Présentation de l'offre</h2>
              <div class="prose-plain text-slate-600 text-sm leading-relaxed">
                {{ product.description || "Abonnement officiel garanti avec livraison directe dans votre espace client." }}
              </div>
            </div>

            <!-- Activation Instructions if any -->
            <div v-if="product.instructions" class="p-4 bg-teal-50/70 border border-teal-200/60 rounded-2xl space-y-2">
              <div class="flex items-center gap-2 text-[#0d5c4a] font-bold text-sm">
                <CheckCircle2 class="size-4 shrink-0" />
                <span>Modalités de livraison et d'activation</span>
              </div>
              <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">
                {{ product.instructions }}
              </p>
            </div>
          </div>
        </div>

        <!-- Right: Pricing & Order action box -->
        <div class="lg:col-span-5">
          <div class="card p-6 sm:p-8 space-y-6 sticky top-24 shadow-pop">
            <div>
              <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Prix d'accès officiel</span>
              <p class="font-display text-3xl sm:text-4xl font-extrabold text-[#0d5c4a] mt-1">
                {{ formattedPrice }}
              </p>
              <p class="text-xs text-slate-500 mt-1">
                Montant total net en FCFA. Aucun frais supplémentaire de conversion.
              </p>
            </div>

            <div class="space-y-3">
              <Link
                v-if="isAvailable"
                :href="`/commande/validation?produit=${encodeURIComponent(product.slug)}`"
                class="btn btn-primary btn-block min-h-12 text-base shadow-glow"
              >
                <ShoppingCart class="size-5" />
                Commander maintenant
              </Link>
              <div
                v-else
                class="alert alert-warning text-center"
              >
                Victime de son succès. Réapprovisionnement en cours.
              </div>
            </div>

            <!-- Trust points -->
            <div class="space-y-3.5 pt-4 border-t border-slate-100 text-xs text-slate-600">
              <div class="flex items-center gap-3">
                <Zap class="size-4 text-[#ff6a13] shrink-0" />
                <span>Livraison automatique dans Mes achats en quelques minutes</span>
              </div>
              <div class="flex items-center gap-3">
                <ShieldCheck class="size-4 text-[#0d5c4a] shrink-0" />
                <span>Payez par Orange Money, Moov, Wave ou Solde</span>
              </div>
              <div class="flex items-center gap-3">
                <Clock class="size-4 text-emerald-600 shrink-0" />
                <span>Assistance technique et garantie de remplacement incluses</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Related Products -->
      <div v-if="related.length" class="space-y-6 pt-10 border-t border-slate-200/80">
        <h2 class="h2 text-xl">Offres similaires dans le même rayon</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <ProductCard
            v-for="rel in related"
            :key="rel.id"
            :product="rel"
          />
        </div>
      </div>
    </div>
  </SiteLayout>
</template>
