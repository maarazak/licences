<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, X, ShoppingBag } from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import ProductCard from '@/Components/Shop/ProductCard.vue';

interface ProductItem {
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
}

const props = defineProps<{
  products: ProductItem[];
  categories: Record<string, number>;
  activeCategory: string;
  searchQuery: string;
}>();

const search = ref(props.searchQuery || '');
let searchTimeout: any = null;

const applyFilter = (category: string) => {
  router.get('/boutique', {
    categorie: category,
    q: search.value || undefined,
  }, { preserveState: true, replace: true });
};

const handleSearchInput = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get('/boutique', {
      categorie: props.activeCategory !== 'toutes' ? props.activeCategory : undefined,
      q: search.value.trim() || undefined,
    }, { preserveState: true, replace: true });
  }, 350);
};

const clearSearch = () => {
  search.value = '';
  router.get('/boutique', {
    categorie: props.activeCategory !== 'toutes' ? props.activeCategory : undefined,
  }, { preserveState: true, replace: true });
};
</script>

<template>
  <SiteLayout>
    <Head title="Boutique — Abonnements et licences numériques" />

    <div class="container-page py-10 space-y-8">
      <!-- Title & Search bar -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
          <h1 class="h1">Boutique de licences</h1>
          <p class="lead mt-1">
            Tous nos abonnements officiels, payables directement en Francs CFA par Mobile Money.
          </p>
        </div>

        <div class="w-full md:w-80 relative">
          <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <input
            v-model="search"
            @input="handleSearchInput"
            type="search"
            placeholder="Filtrer par nom ou marque..."
            class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-10 pr-9 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#0d5c4a] focus:outline-none focus:ring-4 focus:ring-[#0d5c4a]/15 shadow-sm transition"
          />
          <button
            v-if="search"
            type="button"
            @click="clearSearch"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
          >
            <X class="size-4" />
          </button>
        </div>
      </div>

      <!-- Categories Filter Chips -->
      <div class="flex items-center gap-2.5 overflow-x-auto pb-2 scrollbar-none">
        <button
          type="button"
          @click="applyFilter('toutes')"
          :class="[
            'chip text-xs py-2',
            activeCategory === 'toutes' ? 'chip-active' : '',
          ]"
        >
          Tous les produits ({{ Object.values(categories).reduce((a, b) => a + b, 0) || products.length }})
        </button>

        <button
          v-for="(count, cat) in categories"
          :key="cat"
          type="button"
          @click="applyFilter(cat)"
          :class="[
            'chip text-xs py-2',
            activeCategory === cat ? 'chip-active' : '',
          ]"
        >
          {{ cat.toUpperCase() }} ({{ count }})
        </button>
      </div>

      <!-- Active filter indicator -->
      <div v-if="search || activeCategory !== 'toutes'" class="flex items-center gap-2 text-xs text-slate-500">
        <span>Filtres actifs :</span>
        <span v-if="activeCategory !== 'toutes'" class="badge badge-neutral">
          Catégorie : {{ activeCategory }}
        </span>
        <span v-if="search" class="badge badge-neutral">
          Recherche : « {{ search }} »
        </span>
        <button
          type="button"
          @click="() => { search = ''; applyFilter('toutes'); }"
          class="text-[#0d5c4a] font-bold hover:underline ml-2"
        >
          Réinitialiser
        </button>
      </div>

      <!-- Products Grid -->
      <div v-if="products.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <ProductCard
          v-for="product in products"
          :key="product.id"
          :product="product"
        />
      </div>

      <!-- Empty State -->
      <div v-else class="card p-12 text-center max-w-md mx-auto space-y-4">
        <div class="size-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
          <ShoppingBag class="size-8" />
        </div>
        <h3 class="h3 text-lg">Aucun produit trouvé</h3>
        <p class="text-sm text-slate-500">
          Aucun abonnement ne correspond à vos critères de recherche. Essayez d'autres mots-clés ou consultez tous nos rayons.
        </p>
        <button
          type="button"
          @click="() => { search = ''; applyFilter('toutes'); }"
          class="btn btn-primary btn-sm"
        >
          Voir tout le catalogue
        </button>
      </div>
    </div>
  </SiteLayout>
</template>
