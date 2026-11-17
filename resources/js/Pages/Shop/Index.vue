<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import CategoryBubble from '@/Components/Shop/CategoryBubble.vue';
import LoadMoreLink from '@/Components/Shop/LoadMoreLink.vue';
import ProductCard from '@/Components/Shop/ProductCard.vue';
import { useI18n } from '@/Utils/useI18n';

interface CatalogProduct {
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
  isFeatured?: boolean;
}

const props = defineProps<{
  products: CatalogProduct[];
  categories: string[];
  activeCategory: string;
  searchQuery: string;
  availability: string;
  sort: string;
  shown: number;
}>();

const { t } = useI18n();

const PAGE_SIZE = 12;
const currentShown = ref(props.shown || PAGE_SIZE);

const qInput = ref(props.searchQuery || '');
const dispoInput = ref(props.availability === 'available' ? 'disponible' : '');
const sortInput = ref(props.sort || 'featured');

const SORTS = computed(() => [
  { value: 'featured', label: t("Produits phares d'abord") },
  { value: 'price_asc', label: t('Prix croissant') },
  { value: 'price_desc', label: t('Prix décroissant') },
  { value: 'name', label: t('Nom (A à Z)') },
]);

const filtered = computed(() => {
  return !!(props.searchQuery || props.activeCategory || props.availability === 'available');
});

const visibleProducts = computed(() => {
  return props.products.slice(0, currentShown.value);
});

const submitFilter = () => {
  router.get(
    '/boutique',
    {
      q: qInput.value.trim() || undefined,
      categorie: props.activeCategory || undefined,
      dispo: dispoInput.value || undefined,
      tri: sortInput.value === 'featured' ? undefined : sortInput.value,
    },
    { preserveState: true }
  );
};

const handleLoadMore = () => {
  currentShown.value += PAGE_SIZE;
};

const linkForCategory = (cat: string) => {
  const current: Record<string, string | undefined> = {
    q: props.searchQuery || undefined,
    categorie: cat || undefined,
    dispo: props.availability === 'available' ? 'disponible' : undefined,
    tri: props.sort === 'featured' ? undefined : props.sort,
  };
  const searchParams = new URLSearchParams();
  for (const [k, v] of Object.entries(current)) {
    if (v) searchParams.set(k, v);
  }
  const str = searchParams.toString();
  return `/boutique${str ? `?${str}` : ''}`;
};
</script>

<template>
  <SiteLayout>
    <Head :title="`${t('Boutique')} — ${t('Abonnements et licences numériques')}`" />

    <!-- ── En-tête Héros de la Boutique ── -->
    <section class="bg-hero relative overflow-hidden">
      <div aria-hidden="true" class="grid-lines absolute inset-0" />
      <div class="container-page relative py-10 sm:py-12">
        <h1 class="font-display text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">
          {{ t('Boutique') }}
        </h1>
        <p class="mt-2 max-w-xl text-muted">
          {{ t('Abonnements premium payés en FCFA, livrés dans votre espace.') }}
        </p>

        <!-- Formulaire de recherche et filtres direct -->
        <form
          @submit.prevent="submitFilter"
          class="mt-7 flex flex-col gap-3 rounded-2xl border border-line bg-white p-2.5 shadow-card sm:flex-row sm:items-center"
        >
          <div class="relative min-w-0 flex-1">
            <Search class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted" aria-hidden="true" />
            <label for="q" class="sr-only">{{ t('Rechercher un outil') }}</label>
            <input
              id="q"
              v-model="qInput"
              type="search"
              maxlength="80"
              :placeholder="t('Rechercher : Netflix, ChatGPT, Canva…')"
              class="input border-transparent bg-canvas pl-12 shadow-none"
            />
          </div>

          <label for="dispo" class="sr-only">{{ t('Disponibilité') }}</label>
          <select id="dispo" v-model="dispoInput" class="input sm:w-44">
            <option value="">{{ t('Toutes les offres') }}</option>
            <option value="disponible">{{ t('En stock uniquement') }}</option>
          </select>

          <label for="tri" class="sr-only">{{ t('Trier par') }}</label>
          <select id="tri" v-model="sortInput" class="input sm:w-52">
            <option v-for="s in SORTS" :key="s.value" :value="s.value">
              {{ s.label }}
            </option>
          </select>

          <button type="submit" class="btn btn-primary min-h-11 px-7 cursor-pointer">
            {{ t('Rechercher') }}
          </button>
        </form>
      </div>
    </section>

    <!-- ── Contenu du Catalogue ── -->
    <div class="container-page py-8">
      <!-- Puces de rayons / catégories -->
      <div v-if="categories && categories.length" class="-mx-4 flex gap-2.5 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:px-0" role="group" :aria-label="t('Catégories')">
        <Link
          :href="linkForCategory('')"
          :class="[
            'chip shrink-0',
            !activeCategory ? 'chip-active' : '',
          ]"
        >
          {{ t('Tout') }}
        </Link>
        <Link
          v-for="name in categories"
          :key="name"
          :href="linkForCategory(name)"
          :class="[
            'chip shrink-0',
            activeCategory === name ? 'chip-active' : '',
          ]"
        >
          <CategoryBubble :name="name" size="sm" />
          <span>{{ t(name) }}</span>
        </Link>
      </div>

      <!-- Compteur et réinitialisation des filtres -->
      <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm font-semibold text-muted">
          {{ products.length === 1 ? t('1 offre') : t('{count} offres', { count: products.length }) }}
          {{ activeCategory ? ` · ${t(activeCategory)}` : '' }}
        </p>
        <Link v-if="filtered" href="/boutique" class="link text-sm">
          {{ t('Effacer les filtres') }}
        </Link>
      </div>

      <!-- Grille des fiches produits -->
      <template v-if="products.length">
        <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <ProductCard
            v-for="product in visibleProducts"
            :key="product.id"
            :product="product"
            :priority="Boolean(product.is_featured && sort === 'featured' && !filtered)"
          />
        </div>

        <LoadMoreLink
          v-if="products.length > currentShown"
          href="#"
          :label="t('Charger plus')"
          :hint="t('{shown} sur {total} offres affichées', { shown: currentShown, total: products.length })"
          @click.prevent="handleLoadMore"
        />
      </template>

      <!-- État vide si aucune offre trouvée -->
      <div v-else class="card mt-5 px-6 py-14 text-center">
        <p class="h3 text-lg">
          {{ filtered ? t('Aucune offre ne correspond à votre recherche') : t('Les offres arrivent bientôt. Revenez dans un instant.') }}
        </p>
        <div v-if="filtered" class="mt-5">
          <Link href="/boutique" class="btn btn-brand">
            {{ t('Voir toutes les offres') }}
          </Link>
        </div>
      </div>
    </div>
  </SiteLayout>
</template>
