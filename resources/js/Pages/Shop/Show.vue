<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
  ArrowRight,
  ChevronDown,
  KeyRound,
  RefreshCw,
  ShieldCheck,
  ShoppingCart,
  Zap,
} from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import BrandLogo from '@/Components/Shared/BrandLogo.vue';
import BrandTint from '@/Components/Shared/BrandTint.vue';
import { logoFor } from '@/Utils/brands';
import { deliveryKindLabel } from '@/Types/enums';

interface ProductDetail {
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
  description?: string;
  description_en?: string;
  instructions?: string;
  instructions_en?: string;
  requires_email: boolean;
  is_featured: boolean;
}

interface RelatedProduct {
  id: string;
  slug: string;
  name: string;
  title: string;
  display_name?: string;
  shelf: string;
  price_xof: number;
  priceXof: number;
  duration: string;
  available: boolean;
}

const props = defineProps<{
  product: ProductDetail;
  related: RelatedProduct[];
}>();

const resolvedLogo = computed(() => logoFor(props.product.name, props.product.display_name));

const formatPrice = (amount: number) => {
  return `${amount.toLocaleString('fr-FR')} FCFA`;
};

const accessText = computed(() => deliveryKindLabel(props.product.accessKind || props.product.delivery_kind));

const brandName = computed(() => {
  return resolvedLogo.value.label || props.product.title.split(' ')[0] || 'la marque';
});

const steps = [
  { title: 'Payez en FCFA', text: 'Le prix affiché est le prix final.' },
  { title: 'Nous préparons votre accès', text: 'Paiement et livraison sont confirmés séparément et suivis dans Mes achats.' },
  { title: 'Recevez vos instructions', text: "Lien, code ou compte selon l'offre, dans votre espace." },
];

const assurances = [
  { icon: Zap, text: 'Livraison en quelques minutes' },
  { icon: ShieldCheck, text: 'Paiement mobile money sécurisé' },
  { icon: RefreshCw, text: 'Remplacé ou crédité si l’accès échoue' },
  { icon: KeyRound, text: 'Vos accès restent dans Mes achats' },
];

const terms = computed(() => [
  `Type d'accès : ${accessText.value}.`,
  `Durée d'abonnement : ${props.product.duration}.`,
  `Garantie : ${props.product.warranty || 'Garantie intégrale durant toute la période'}.`,
  'Livraison numérique dans votre espace « Mes achats » avec notification par e-mail.',
  'En cas d’impossibilité de livraison, vous choisissez entre un avoir immédiat ou un remboursement.',
]);
</script>

<template>
  <SiteLayout>
    <Head :title="`${product.title} — Acheter en FCFA`" />

    <div class="container-page py-8">
      <!-- ── Fil d'Ariane ── -->
      <nav aria-label="Fil d'Ariane" class="flex flex-wrap items-center gap-2 text-xs font-medium text-muted">
        <Link href="/boutique" class="hover:text-ink">Boutique</Link>
        <span aria-hidden="true">/</span>
        <template v-if="product.shelf">
          <Link :href="`/boutique?categorie=${encodeURIComponent(product.shelf)}`" class="hover:text-ink">
            {{ product.shelf }}
          </Link>
          <span aria-hidden="true">/</span>
        </template>
        <span class="text-ink">{{ product.title }}</span>
      </nav>

      <!-- ── Carte d'en-tête Produit avec Voile de Marque ── -->
      <header class="card relative mt-5 overflow-hidden">
        <BrandTint :logo="resolvedLogo" :strength="0.14" />
        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-r from-white/0 via-white/40 to-white" />
        <div class="relative flex flex-wrap items-center gap-6 p-6 sm:p-8">
          <BrandLogo :logo="resolvedLogo" size="xl" />
          <div class="min-w-0 flex-1">
            <p v-if="product.shelf" class="eyebrow text-accent-ink">{{ product.shelf }}</p>
            <h1 translate="no" class="h1 mt-1 leading-tight">{{ product.title }}</h1>
            <div class="mt-3 flex flex-wrap items-center gap-2">
              <span class="badge badge-neutral">{{ product.duration }}</span>
              <span class="badge badge-neutral">{{ accessText }}</span>
              <span v-if="product.available" class="badge badge-success">Disponible</span>
              <span v-else class="badge badge-danger">Rupture temporaire</span>
            </div>
          </div>
        </div>
      </header>

      <!-- ── Corps de Fiche en Deux Colonnes ── -->
      <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_360px] lg:items-start">
        <!-- Colonne Gauche : Détails & Présentation -->
        <div class="min-w-0 space-y-9">
          <p v-if="product.description" class="text-lg leading-relaxed text-ink/85">
            {{ product.description }}
          </p>

          <!-- Ce que vous achetez -->
          <section>
            <h2 class="h3 text-lg">Ce que vous achetez</h2>
            <div class="card mt-3 px-5 divide-y divide-line">
              <div class="flex items-center justify-between py-3 text-sm">
                <span class="text-muted">Type d'accès</span>
                <span class="font-semibold text-ink">{{ accessText }}</span>
              </div>
              <div class="flex items-center justify-between py-3 text-sm">
                <span class="text-muted">Durée</span>
                <span class="font-semibold text-ink">{{ product.duration }}</span>
              </div>
              <div class="flex items-center justify-between py-3 text-sm">
                <span class="text-muted">Garantie</span>
                <span class="font-semibold text-ink">{{ product.warranty || 'Garantie intégrale' }}</span>
              </div>
              <div v-if="product.requires_email" class="flex items-center justify-between py-3 text-sm">
                <span class="text-muted">Activation</span>
                <span class="font-semibold text-ink">Sur l'adresse e-mail fournie</span>
              </div>
            </div>
          </section>

          <!-- Comment ça se passe -->
          <section>
            <h2 class="h3 text-lg">Comment ça se passe</h2>
            <ol class="mt-3 grid gap-3 sm:grid-cols-3">
              <li v-for="(st, i) in steps" :key="st.title" class="card p-4">
                <span class="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-brand-bright to-brand font-display text-sm font-extrabold text-white">
                  {{ i + 1 }}
                </span>
                <p class="mt-3 text-sm font-bold text-ink">{{ st.title }}</p>
                <p class="mt-1 text-xs leading-relaxed text-muted">{{ st.text }}</p>
              </li>
            </ol>
          </section>

          <!-- Mode d'emploi -->
          <section v-if="product.instructions">
            <h2 class="h3 text-lg">Mode d'emploi</h2>
            <div class="card mt-3 p-5">
              <p class="text-sm leading-relaxed text-ink whitespace-pre-line">
                {{ product.instructions }}
              </p>
            </div>
          </section>

          <!-- Conditions de l'offre -->
          <details class="card group px-5 py-4">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-bold text-ink">
              <span>Conditions de l'offre</span>
              <span class="text-xs font-semibold text-brand group-open:hidden">Voir toutes les conditions</span>
              <span class="hidden text-xs font-semibold text-muted group-open:inline">Masquer</span>
            </summary>
            <ul class="mt-3 space-y-2 text-sm leading-relaxed text-muted list-disc list-inside">
              <li v-for="term in terms" :key="term">{{ term }}</li>
            </ul>
          </details>

          <!-- Autres formules de la marque -->
          <section v-if="related && related.length">
            <h2 class="h3 text-lg">Autres formules {{ brandName }}</h2>
            <ul class="mt-3 space-y-2.5">
              <li v-for="item in related" :key="item.id">
                <Link
                  :href="`/boutique/${item.slug}`"
                  class="group card flex flex-wrap items-center gap-3 p-3 pr-4 transition duration-300 hover:-translate-y-0.5 hover:shadow-pop"
                >
                  <BrandLogo :logo="logoFor(item.name, item.display_name)" size="sm" />
                  <span translate="no" class="min-w-0 flex-1 text-sm font-bold text-ink">
                    {{ item.title }}
                  </span>
                  <span class="text-xs text-muted">{{ item.duration }}</span>
                  <span class="font-display text-sm font-extrabold text-ink">
                    {{ formatPrice(item.priceXof) }}
                  </span>
                  <ArrowRight class="size-4 text-muted transition group-hover:translate-x-0.5 group-hover:text-brand" />
                </Link>
              </li>
            </ul>
          </section>
        </div>

        <!-- Colonne Droite : Boîte d'Achat Sticky -->
        <aside class="space-y-4">
          <div class="card p-6 sm:p-8 space-y-6 sticky top-24 shadow-pop">
            <div>
              <p class="text-xs font-bold text-muted uppercase tracking-wider">Prix de l'offre</p>
              <p class="font-display text-3xl font-extrabold text-ink mt-1">
                {{ formatPrice(product.priceXof) }}
              </p>
              <p class="text-xs text-muted mt-1">
                Paiement direct en FCFA par Mobile Money (Orange, Moov, MTN, Wave).
              </p>
            </div>

            <div>
              <Link
                v-if="product.available"
                :href="`/commande/validation?produit=${encodeURIComponent(product.slug)}`"
                class="btn btn-primary btn-block min-h-12 text-base font-bold shadow-md"
              >
                <ShoppingCart class="size-5" />
                <span>Acheter maintenant</span>
              </Link>
              <div v-else class="space-y-3">
                <div class="card p-3 bg-amber-50/60 border-amber-200 text-amber-800 text-xs text-center font-medium">
                  Cette offre est momentanément en rupture.
                </div>
                <Link
                  href="/compte/alertes"
                  class="btn btn-soft btn-block min-h-11 text-xs"
                >
                  M'alerter par e-mail au retour
                </Link>
              </div>
            </div>

            <!-- Points de réassurance -->
            <ul class="space-y-3 border-t border-line pt-4 text-xs font-semibold text-ink/80">
              <li v-for="ass in assurances" :key="ass.text" class="flex items-center gap-2.5">
                <component :is="ass.icon" class="size-4 text-brand shrink-0" />
                <span>{{ ass.text }}</span>
              </li>
            </ul>
          </div>
        </aside>
      </div>
    </div>
  </SiteLayout>
</template>
